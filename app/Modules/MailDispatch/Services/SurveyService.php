<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\SurveyResponseModel;
use App\Modules\MailDispatch\Models\SurveyTokenModel;
use App\Modules\Provisioning\Services\CredentialCipher;

/**
 * CSAT survey appended below the agent's signature on every reply sent from
 * Nexus. One token per CONVERSATION (not per message, not per recipient): the
 * reply is a single email shared by the requester and every copied address, so
 * a per-recipient link is not possible without splitting the send. Instead the
 * token is a SHARED QUOTA — it accepts up to `survey_max_responses` answers
 * (default 3) before it closes, so more than one copied person can weigh in on
 * the same thread. There is still no per-person attribution: the quota caps
 * the noise, it does not identify who answered.
 *
 * Token life cycle: a GET (open) never consumes it — Outlook Safe Links and
 * mail-gateway scanners fetch the link before the human does. Only a POST
 * (submit) consumes one slot of the quota, atomically (see
 * SurveyTokenModel::reserveResponseSlot()). The same token is reused across
 * every reply of the thread until the quota is full, so an older email in the
 * conversation keeps working; each reuse extends `expires_at`. See the
 * migration docblocks (2026-09-24-100001, 2026-09-25-100001) for why the life
 * cycle (tokens) and the immutable facts (responses) are split tables, and for
 * the shared-quota model itself.
 */
class SurveyService
{
    public function __construct(
        private MailDispatchSettings $settings,
        private SurveyTokenModel $tokens,
        private SurveyResponseModel $responses,
        private ConversationModel $conversations,
        private EventModel $events,
        private ?CredentialCipher $cipher = null,
    ) {
        $this->cipher ??= new CredentialCipher();
    }

    // -----------------------------------------------------------------------
    // Emission (called from SmtpReplyService / ReplyService, before send)
    // -----------------------------------------------------------------------

    /**
     * The HTML block to append to the outgoing reply, below the signature.
     * '' when the survey is off, the thread has no valid requester address, or
     * it was already answered and reissuing is disabled — callers must treat
     * an empty string as "append nothing", never as an error.
     */
    public function replyBlock(int $conversationId, int $userId, string $to, array $cc = []): string
    {
        if (! $this->settings->surveyEnabled() || ! $this->settings->isSendEnabled()) {
            return '';
        }
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        if ($this->conversations->find($conversationId) === null) {
            return '';
        }

        $ttlDays = $this->settings->surveyTtlDays();
        $max     = $this->settings->surveyMaxResponses();
        $ccStr   = $cc !== [] ? implode(', ', $cc) : '';

        $live = $this->tokens->liveFor($conversationId, $max);
        if ($live !== null) {
            $plain = $this->cipher->decrypt((string) ($live['token_plain_enc'] ?? ''));
            if ($plain !== '') {
                $this->tokens->extend((int) $live['id'], $ttlDays);
                $this->tokens->recordSend((int) $live['id'], $to, $ccStr);
            } else {
                // Should not happen (nulled only once the quota is full or the
                // token is revoked; if the ciphertext ever fails to decrypt —
                // e.g. a rotated encryption key — fall back to a fresh token.
                // Revoke the old one first: it still had quota left (that is
                // why liveFor() picked it), and if survey_max_responses is
                // raised later it could otherwise start accepting answers
                // again in parallel with the new token, over the real quota.
                $this->tokens->revoke((int) $live['id']);
                $plain = $this->issueToken($conversationId, (int) $live['cycle'], $userId, $ttlDays, $to, $ccStr);
            }
        } else {
            $latest    = $this->tokens->latestFor($conversationId);
            $exhausted = $latest !== null && (int) $latest['response_count'] >= $max;
            if ($exhausted && ! $this->settings->surveyReissueAfterResponse()) {
                return '';
            }
            $cycle = $exhausted ? ((int) $latest['cycle']) + 1 : 1;
            $plain = $this->issueToken($conversationId, $cycle, $userId, $ttlDays, $to, $ccStr);
        }

        return $this->renderBlock($this->urlFor($plain));
    }

    /**
     * Same markup as the real block, for the live preview in Administration.
     * Takes the copy as arguments (not read from settings) so the admin sees
     * unsaved edits reflected immediately, against a placeholder URL.
     */
    public function previewBlockHtml(string $title, string $text, string $cta): string
    {
        return $this->buildBlockHtml($title, $text, $cta, base_url('survey/0000000000000000000000000000000000000000000000000000000000000000'));
    }

    private function issueToken(int $conversationId, int $cycle, int $userId, int $ttlDays, string $to, string $cc): string
    {
        $plain = bin2hex(random_bytes(32));
        $id    = $this->tokens->insert([
            'conversation_id' => $conversationId,
            'token_hash'      => hash('sha256', $plain),
            'token_plain_enc' => $this->cipher->encrypt($plain),
            'cycle'           => $cycle,
            'issued_by'       => $userId > 0 ? $userId : null,
            'expires_at'      => date('Y-m-d H:i:s', strtotime("+{$ttlDays} days")),
        ], true);
        $this->tokens->recordSend((int) $id, $to, $cc);

        return $plain;
    }

    private function renderBlock(string $url): string
    {
        return $this->buildBlockHtml(
            $this->settings->surveyBlockTitle(),
            $this->settings->surveyBlockText(),
            $this->settings->surveyBlockCta(),
            $url
        );
    }

    /**
     * The survey block's markup. A card (tinted background, rounded corners,
     * a color bar on top) rather than a bare paragraph + link, so it reads as
     * a deliberate piece of the email instead of an afterthought — while
     * staying inside what Outlook's rendering engine tolerates: no external
     * CSS, no images, no border-radius/box-shadow relied on for legibility
     * (Outlook just squares the corners and drops the shadow; everything
     * else still reads fine).
     */
    private function buildBlockHtml(string $title, string $text, string $cta, string $url): string
    {
        $safeUrl = esc($url, 'attr');

        // table-layout:fixed pins both wrapper tables to their declared
        // width="100%" instead of growing to fit their widest cell; without
        // it, the raw fallback URL below — one long unbreakable token, no
        // spaces for the browser to wrap on — stretches the whole card past
        // its container. word-break on the URL itself is the other half: it
        // lets that token wrap mid-string once it does hit the edge.
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:26px;table-layout:fixed;">'
            . '<tr><td>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
            . 'style="background-color:#eef5fc;border:1px solid #d3e3f5;border-radius:14px;table-layout:fixed;">'
            . '<tr><td style="background-color:#1773c8;height:4px;line-height:4px;font-size:1px;border-radius:14px 14px 0 0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:26px 28px;font-family:Arial,Helvetica,sans-serif;">'
            . '<p style="margin:0 0 8px;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#1773c8;">Encuesta rápida</p>'
            . '<p style="margin:0 0 8px;font-size:17px;line-height:1.4;font-weight:bold;color:#1f2733;">' . esc($title) . '</p>'
            . '<p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#5b6673;">' . esc($text) . '</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td style="background-color:#1773c8;border-radius:24px;">'
            . '<a href="' . $safeUrl . '" target="_blank" '
            . 'style="display:inline-block;padding:14px 30px;font-family:Arial,Helvetica,sans-serif;'
            . 'font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:24px;">'
            . esc($cta) . '&nbsp;&rarr;</a>'
            . '</td></tr></table>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>';
    }

    private function urlFor(string $plainToken): string
    {
        return base_url('survey/' . $plainToken);
    }

    // -----------------------------------------------------------------------
    // Public side (Survey controller)
    // -----------------------------------------------------------------------

    /**
     * Resolves a token from the public URL without consuming it.
     *
     * @return array{status:'ok'|'full'|'unavailable', token?:array, conversation?:array}
     */
    public function resolve(string $plainToken): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return ['status' => 'unavailable'];
        }
        $token = $this->tokens->findByToken($plainToken);
        if ($token === null || $token['revoked_at'] !== null) {
            return ['status' => 'unavailable'];
        }
        if ((int) $token['response_count'] >= $this->settings->surveyMaxResponses()) {
            return ['status' => 'full'];
        }
        if (! $this->settings->surveyEnabled()) {
            return ['status' => 'unavailable'];
        }
        if (strtotime((string) $token['expires_at']) < time()) {
            return ['status' => 'unavailable'];
        }
        $conv = $this->conversations->find((int) $token['conversation_id']);
        if ($conv === null) {
            return ['status' => 'unavailable'];
        }

        return ['status' => 'ok', 'token' => $token, 'conversation' => $conv];
    }

    /** GET: resolves and records the open, but never consumes the token. */
    public function open(string $plainToken, string $ip, string $userAgent): array
    {
        $res = $this->resolve($plainToken);
        if ($res['status'] === 'ok') {
            $this->tokens->noteOpen((int) $res['token']['id'], $res['token'], $this->hashIp($ip), $userAgent);
        }

        return $res;
    }

    /**
     * POST: consumes one response slot. reserveResponseSlot() decides a
     * concurrent double-submit at the database (a single conditioned UPDATE),
     * not with a try/catch here.
     *
     * @return array{status:'ok'|'full'|'unavailable'|'invalid'}
     */
    public function submit(string $plainToken, int $rating, string $resolved, string $comment, string $ip, string $userAgent): array
    {
        $res = $this->resolve($plainToken);
        if ($res['status'] !== 'ok') {
            return $res;
        }
        if ($rating < 1 || $rating > 5 || ! in_array($resolved, ['yes', 'partial', 'no'], true)) {
            return ['status' => 'invalid'];
        }

        $token   = $res['token'];
        $conv    = $res['conversation'];
        $comment = mb_substr(trim($comment), 0, 1000);
        $max     = $this->settings->surveyMaxResponses();
        $tokenId = (int) $token['id'];

        // Reserve the slot BEFORE inserting, in a transaction: if the insert
        // fails, the rollback also undoes the reservation instead of burning it.
        $db = \Config\Database::connect();
        $db->transBegin();

        if (! $this->tokens->reserveResponseSlot($tokenId, $max)) {
            $db->transRollback();

            // Quota filled between resolve() and here — lost the race.
            return ['status' => 'full'];
        }

        try {
            $ok = $this->responses->insert([
                'token_id'        => $tokenId,
                'conversation_id' => (int) $token['conversation_id'],
                'agent_id'        => isset($conv['agent_id']) && $conv['agent_id'] !== null ? (int) $conv['agent_id'] : null,
                'rating'          => $rating,
                'resolved'        => $resolved,
                'comment'         => $comment !== '' ? $comment : null,
                'requester_email' => $conv['requester_email'] ?? null,
                'ip_hash'         => $this->hashIp($ip),
                'user_agent'      => $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null,
                'responded_at'    => date('Y-m-d H:i:s'),
            ]) !== false;
        } catch (\Throwable $e) {
            $ok = false;
            log_message('error', '[SurveyService] insert failed for token id ' . $tokenId . ': ' . $e->getMessage());
        }

        if (! $ok) {
            $db->transRollback();

            return ['status' => 'unavailable'];
        }
        $db->transCommit();

        // Outside the transaction: the slot number only decorates the log entry.
        $slot = (int) ($this->tokens->find($tokenId)['response_count'] ?? 0);

        $this->events->log(
            (int) $token['conversation_id'],
            'survey',
            null,
            null,
            (string) $rating,
            'Encuesta respondida (' . $slot . ' de ' . $max . '): calificación ' . $rating . '/5, resuelto: '
                . $this->resolvedLabel($resolved) . '.' . ($comment !== '' ? ' Comentario: ' . $comment : '')
        );

        return ['status' => 'ok'];
    }

    /** Never persisted: only used to pseudonymize the requester's IP. */
    private function hashIp(string $ip): string
    {
        if ($ip === '') {
            return '';
        }
        $salt = (string) (env('encryption.key') ?: 'nexus-survey-salt');

        return hash('sha256', $ip . '|' . $salt);
    }

    public function resolvedLabel(string $v): string
    {
        return match ($v) {
            'yes'     => 'Sí',
            'partial' => 'Parcial',
            'no'      => 'No',
            default   => $v,
        };
    }

    // -----------------------------------------------------------------------
    // Reads (thread card + supervisor screen)
    // -----------------------------------------------------------------------

    /**
     * The state of a conversation's survey, for the inline card in the
     * thread AND for GET /api/v1/dispatch/conversations/{id}/survey (this
     * exact array). Null when no survey was ever issued.
     *
     * `responses` carries EVERY answer received (several people of the
     * thread, and several cycles if it was reopened); `used`/`remaining`
     * describe the current token, the only one still able to take more.
     * `state` and "has responses" are independent: a token can be 'pending'
     * with responses already in, or 'full' with none if the admin lowered
     * the quota to 0 (defensively clamped, see `remaining` below).
     *
     * @return ?array{
     *   state:'pending'|'full'|'expired'|'revoked', token:array,
     *   responses:array<int,array<string,mixed>>,
     *   count:int, used:int, max:int, remaining:int
     * }
     */
    public function forConversation(int $conversationId): ?array
    {
        $token = $this->tokens->latestFor($conversationId);
        if ($token === null) {
            return null;
        }

        $responses = $this->responses->allForConversation($conversationId);
        $max       = $this->settings->surveyMaxResponses();
        $used      = (int) $token['response_count'];
        $remaining = max(0, $max - $used); // the admin may have lowered max afterwards

        $state = 'pending';
        if ($token['revoked_at'] !== null) {
            $state = 'revoked';
        } elseif ($remaining === 0) {
            $state = 'full';
        } elseif (strtotime((string) $token['expires_at']) < time()) {
            $state = 'expired';
        }

        return [
            'state'     => $state,
            'token'     => $token,
            'responses' => $responses,
            'count'     => count($responses),
            'used'      => $used,
            'max'       => $max,
            'remaining' => $remaining,
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>, total:int} */
    public function list(array $filters, int $page = 1, int $perPage = 50): array
    {
        return $this->responses->search($filters, $page, $perPage);
    }

    public function stats(array $filters): array
    {
        return $this->responses->stats($filters);
    }

    public function agentsInRange(array $filters): array
    {
        return $this->responses->agentsInRange($filters);
    }

    /** CSV with a UTF-8 BOM (Excel-friendly accents), mirrors MailDispatchMetrics::conversationsCsv(). */
    public function csv(array $filters): string
    {
        $rows = $this->responses->forCsv($filters);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Fecha', 'Conversación ID', 'Asunto', 'Solicitante', 'Agente', 'Calificación', 'Resuelto', 'Comentario']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['responded_at'], $r['conversation_id'], $r['conversation_subject'], $r['requester_name'],
                $r['agent_name'], $r['rating'], $this->resolvedLabel((string) $r['resolved']), $r['comment'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return "\xEF\xBB\xBF" . $csv;
    }
}
