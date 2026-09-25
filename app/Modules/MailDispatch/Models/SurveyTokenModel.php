<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * The CSAT survey token's life cycle: at most one LIVE row per conversation
 * (still has quota, i.e. `response_count < max`, AND `revoked_at IS NULL`).
 * See the migration docblock for why this is split from the response, and
 * 2026-09-25-100001_AddSurveyMultiResponse for the shared-quota model.
 */
class SurveyTokenModel extends Model
{
    protected $table         = 'maildispatch_survey_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'conversation_id', 'token_hash', 'token_plain_enc', 'cycle', 'issued_by',
        'sent_count', 'last_sent_at', 'last_sent_to', 'last_sent_cc',
        'expires_at', 'first_opened_at', 'last_opened_at', 'open_count',
        'open_ip_hash', 'open_user_agent', 'responded_at', 'last_responded_at',
        'response_count', 'revoked_at',
    ];

    /** The one live token of a conversation (has quota left, unrevoked), if any. */
    public function liveFor(int $conversationId, int $maxResponses): ?array
    {
        return $this->where('conversation_id', $conversationId)
            ->where('response_count <', $maxResponses)
            ->where('revoked_at', null)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Most recent token of a conversation regardless of state, for display. */
    public function latestFor(int $conversationId): ?array
    {
        return $this->where('conversation_id', $conversationId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Resolves an incoming public request. Does not filter by state — the
     *  caller distinguishes "answered" / "expired" / "revoked" itself, so it
     *  can show "ya respondida" without confirming an unknown token exists. */
    public function findByToken(string $plainToken): ?array
    {
        return $this->where('token_hash', hash('sha256', $plainToken))->first();
    }

    /**
     * Reserves one response slot in a single statement: the WHERE requires
     * quota still left, so two concurrent POSTs racing for the last slot let
     * exactly one through — the database decides the race, not an `if` here.
     *
     * The SET order is not cosmetic: MySQL evaluates assignments left to
     * right using each row's ALREADY-updated values. token_plain_enc goes
     * first so it still reads the old response_count; moved after the
     * increment, the link would close one slot early, with no visible error.
     *
     * @return bool true when this call actually claimed a slot.
     */
    public function reserveResponseSlot(int $id, int $maxResponses): bool
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE ' . $this->table . ' SET'
            . ' token_plain_enc   = CASE WHEN response_count + 1 >= ? THEN NULL ELSE token_plain_enc END,'
            . ' responded_at      = COALESCE(responded_at, ?),'
            . ' last_responded_at = ?,'
            . ' response_count    = response_count + 1,'
            . ' updated_at        = ?'
            . ' WHERE id = ? AND revoked_at IS NULL AND response_count < ?',
            [$maxResponses, $now, $now, $now, $id, $maxResponses]
        );

        return $this->db->affectedRows() > 0;
    }

    /**
     * Closes a token for good. Used when replyBlock()'s decrypt-failure
     * fallback issues a fresh token: without this, the old one — no longer
     * resendable by email, but still resolvable by its own hash — could
     * accept responses in parallel with the new one if the admin later
     * raises survey_max_responses, silently exceeding the real per-thread quota.
     */
    public function revoke(int $id): void
    {
        $this->update($id, ['revoked_at' => date('Y-m-d H:i:s'), 'token_plain_enc' => null]);
    }

    public function extend(int $id, int $ttlDays): void
    {
        $this->update($id, ['expires_at' => date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"))]);
    }

    /** Appends a send: bumps sent_count and snapshots who it went to. */
    public function recordSend(int $id, string $to, string $cc): void
    {
        $this->set('sent_count', 'sent_count + 1', false)->where('id', $id)->update();
        $this->update($id, [
            'last_sent_at' => date('Y-m-d H:i:s'),
            'last_sent_to' => $to !== '' ? $to : null,
            'last_sent_cc' => $cc !== '' ? $cc : null,
        ]);
    }

    /** GET open: never consumes. first_opened_at is set once, the rest every time. */
    public function noteOpen(int $id, array $row, string $ipHash, string $userAgent): void
    {
        $now  = date('Y-m-d H:i:s');
        $data = [
            'last_opened_at'  => $now,
            'open_count'      => (int) ($row['open_count'] ?? 0) + 1,
            'open_ip_hash'    => $ipHash !== '' ? $ipHash : ($row['open_ip_hash'] ?? null),
            'open_user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 255) : ($row['open_user_agent'] ?? null),
        ];
        if (empty($row['first_opened_at'])) {
            $data['first_opened_at'] = $now;
        }
        $this->update($id, $data);
    }
}
