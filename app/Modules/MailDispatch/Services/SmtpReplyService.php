<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;
use App\Modules\MailDispatch\Models\SignatureModel;
use Config\Email as EmailConfig;

/**
 * Reply-from-Nexus for the IMAP provider. IMAP is read-only, so replies are sent
 * over SMTP with the configured helpdesk credentials. Threading is preserved by
 * setting In-Reply-To / References to the latest message of the conversation and
 * prefixing the subject with "Re:", so the reply lands in the same thread on the
 * customer's client.
 *
 * Mirrors ReplyService (Graph phase 3): same public signature, gated by the same
 * send_from_nexus_enabled switch. The sent message is recorded immediately with
 * the Message-ID we generate; when its forwarded copy re-syncs from IMAP, the
 * message-id guard in ConversationService::ingest skips it (no duplicate).
 */
class SmtpReplyService
{
    public function __construct(
        private MailDispatchSettings $settings,
        private ConversationModel $conversations,
        private MessageModel $messages,
        private EventModel $events,
        private AttachmentService $attachments
    ) {}

    /** Máximo de copias manuales por respuesta, para acotar el volumen SMTP. */
    private const MAX_CC = 10;

    /**
     * @param array  $files UploadedFile[] posted with the reply (already the
     *                      validated set, or raw — validated here defensively).
     * @param string $cc    Copias escritas a mano por el agente (separadas por
     *                      coma o punto y coma). Vacío por defecto: la respuesta
     *                      va solo al solicitante.
     */
    public function reply(int $conversationId, string $body, int $userId, array $files = [], string $cc = ''): ServiceResult
    {
        if (! $this->settings->isSendEnabled()) {
            return ServiceResult::fail('La respuesta desde Nexus está deshabilitada o el SMTP no está configurado.');
        }
        $body = trim($body);
        if ($body === '') {
            return ServiceResult::fail('La respuesta está vacía.');
        }

        $conv = $this->conversations->find($conversationId);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        if ((string) $conv['status'] === 'cerrada') {
            return ServiceResult::fail('La conversación está cerrada; reábrela para responder.');
        }

        $to = trim((string) ($conv['requester_email'] ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('La conversación no tiene un correo de destinatario válido.');
        }

        $ccRes = $this->parseCc($cc, $to);
        if (! $ccRes->success) {
            return $ccRes;
        }
        $ccList = (array) $ccRes->data;

        // Validate attachments before sending.
        $vres = $this->attachments->validateUploads($files);
        if (! $vres->success) {
            return $vres;
        }
        $validFiles = (array) $vres->data;

        $target = $this->latestReplyTarget($conversationId);
        // The agent's own message (HTML accepted; plain text keeps line breaks),
        // followed by their configured signature. This pair is stored and shown
        // in Nexus as the outbound message.
        $replyHtml = $this->normalizeBody($body);
        $bodyHtml  = $replyHtml . $this->signatureHtml($userId);
        // What actually goes out also quotes the prior thread underneath, so it
        // reads like a real reply sent from the mailbox (history is preserved).
        $sendHtml  = $bodyHtml . $this->historyHtml($conversationId);

        // Effective SMTP config (Core or module-owned, per the smtp_use_core toggle).
        $smtp      = $this->settings->effectiveSmtp();
        $fromEmail = $smtp['from_email'];
        // Our own Message-ID so the re-synced sent copy can be de-duplicated.
        $domain    = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $messageId = '<nexus-' . bin2hex(random_bytes(12)) . '@' . $domain . '>';

        $subject = (string) ($conv['subject'] ?? '');
        if (stripos($subject, 're:') !== 0) {
            $subject = 'Re: ' . $subject;
        }

        $send = $this->send($smtp, $to, $subject, $sendHtml, $messageId, $target, $validFiles, $ccList);
        if (! $send->success) {
            return $send;
        }

        $now = date('Y-m-d H:i:s');

        $messageDbId = (int) $this->messages->insert([
            'conversation_id'     => $conversationId,
            'graph_id'            => 'nexus:' . bin2hex(random_bytes(10)),
            'internet_message_id' => $messageId,
            'in_reply_to'         => $target['internet_message_id'] ?? null,
            'direction'           => 'out',
            'from_name'           => $smtp['from_name'],
            'from_email'          => $fromEmail,
            'to_recipients'       => $to,
            'cc_recipients'       => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject'             => $subject,
            'body_preview'        => mb_substr(trim((string) preg_replace('/\s+/', ' ', strip_tags($replyHtml))), 0, 255),
            'body'                => $bodyHtml,
            'body_is_html'        => 1,
            'has_attachments'     => $validFiles !== [] ? 1 : 0,
            'received_at'         => $now,
        ], true);

        // Keep a copy of the sent files in the thread.
        if ($messageDbId > 0 && $validFiles !== []) {
            $this->attachments->storeOutgoing($messageDbId, $conversationId, $validFiles);
        }

        $fromStatus = (string) $conv['status'];
        $this->conversations->set('message_count', 'message_count + 1', false)
            ->where('id', $conversationId)->update();
        $this->conversations->update($conversationId, [
            'status'            => 'respondida',
            'first_response_at' => $conv['first_response_at'] ?: $now,
            'last_activity_at'  => $now,
        ]);

        $this->events->log($conversationId, 'status', $userId, $fromStatus, 'respondida', 'Respuesta enviada desde Nexus (SMTP).');

        return ServiceResult::ok(null, 'Respuesta enviada al hilo.');
    }

    /**
     * Sends the reply over SMTP with the stored credentials and threading
     * headers. Returns a ServiceResult; the SMTP debug output is logged, never
     * surfaced verbatim to the agent.
     */
    private function send(array $smtp, string $to, string $subject, string $html, string $messageId, ?array $target, array $files = [], array $cc = []): ServiceResult
    {
        $config = new EmailConfig();
        $config->protocol   = 'smtp';
        $config->SMTPHost   = $smtp['host'];
        $config->SMTPUser   = $smtp['user'];
        $config->SMTPPass   = $smtp['pass'];
        $config->SMTPPort   = $smtp['port'];
        $config->SMTPCrypto = $smtp['crypto'];
        $config->mailType   = 'html';
        $config->charset    = 'UTF-8';
        $config->wordWrap   = true;

        $email = \Config\Services::email($config, false);
        $email->setFrom($smtp['from_email'], $smtp['from_name']);
        $email->setTo($to);
        if ($cc !== []) {
            $email->setCC(implode(',', $cc));
        }
        $email->setSubject($subject);
        $email->setMessage($html);
        $email->setHeader('Message-ID', $messageId);

        // Threading: reply under the latest message of the conversation.
        if ($target !== null) {
            $inReplyTo = trim((string) ($target['internet_message_id'] ?? ''));
            if ($inReplyTo !== '') {
                $email->setHeader('In-Reply-To', $inReplyTo);
                $references = trim((string) ($target['references_header'] ?? ''));
                $references = $references !== '' ? $references . ' ' . $inReplyTo : $inReplyTo;
                $email->setHeader('References', $references);
            }
        }

        // Attach the uploaded files. Pass the raw bytes as a buffer (with a
        // non-empty mime): CI4's Email::attach() treats the first arg as a file
        // PATH only when $mime is ''. Previously we passed both a path AND a mime,
        // so CI used the *path string* as the attachment content -> 0 KB / corrupt.
        foreach ($files as $file) {
            if (! ($file instanceof \CodeIgniter\HTTP\Files\UploadedFile) || ! $file->isValid()) {
                continue;
            }
            $content = @file_get_contents($file->getTempName());
            if ($content === false) {
                continue;
            }
            $mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $email->attach($content, 'attachment', $file->getClientName(), $mime);
        }

        if (! $email->send(false)) {
            log_message('error', '[SmtpReplyService] send failed: ' . $email->printDebugger(['headers']));
            return ServiceResult::fail('No se pudo enviar la respuesta por SMTP. Revisa las credenciales SMTP.');
        }

        return ServiceResult::ok();
    }

    /**
     * Parses the agent's manual Cc field into a clean address list.
     *
     * Copies are never derived from the original To/Cc of the thread: every
     * extra recipient is typed on purpose, so a busy thread does not multiply
     * the mail our SMTP has to push. The requester is dropped (already the To)
     * and duplicates are collapsed.
     *
     * @return ServiceResult data = string[] of validated addresses
     */
    private function parseCc(string $raw, string $to): ServiceResult
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ServiceResult::ok([]);
        }

        $parts = preg_split('/[,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $valid   = [];
        $invalid = [];
        $toLower = strtolower($to);
        foreach ($parts as $part) {
            $addr = strtolower(trim($part, " \t\n\r\0\x0B<>"));
            if (! filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $part;
                continue;
            }
            if ($addr === $toLower) {
                continue; // ya va como destinatario principal
            }
            $valid[$addr] = $addr;
        }

        if ($invalid !== []) {
            return ServiceResult::fail('Correo inválido en copia: ' . implode(', ', $invalid));
        }
        if (count($valid) > self::MAX_CC) {
            return ServiceResult::fail('Demasiadas copias: el máximo es ' . self::MAX_CC . ' por respuesta.');
        }

        return ServiceResult::ok(array_values($valid));
    }

    /** Latest message to reply under: prefer the most recent inbound message. */
    private function latestReplyTarget(int $conversationId): ?array
    {
        $inbound = $this->messages
            ->where('conversation_id', $conversationId)
            ->where('direction', 'in')
            ->where("graph_id NOT LIKE 'nexus:%'", null, false)
            ->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')
            ->first();
        if ($inbound) {
            return $inbound;
        }
        return $this->messages
            ->where('conversation_id', $conversationId)
            ->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Normalizes the composed body. If it already contains HTML (rich editor:
     * tables, lists, formatting), it is sanitized and kept as-is. Otherwise it is
     * plain text, escaped with line breaks preserved.
     */
    private function normalizeBody(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // Looks like HTML if it carries a real tag.
        if (preg_match('/<([a-z][a-z0-9]*)\b[^>]*>/i', $raw)) {
            return $this->sanitizeHtml($raw);
        }
        return nl2br(esc($raw));
    }

    /**
     * Minimal HTML sanitizer for agent-composed replies: strips scripts, styles,
     * comments, event-handler attributes and javascript: URIs, while keeping
     * formatting and tables intact. Agents are trusted; this guards the recipient.
     */
    private function sanitizeHtml(string $html): string
    {
        // Drop dangerous elements entirely (with their content).
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#is', '', $html) ?? $html;
        // Strip HTML comments (can hide conditionals).
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        // Remove inline event handlers: on*="..." / on*='...' / on*=value.
        $html = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/i', '', $html) ?? $html;
        // Neutralize javascript: URIs in href/src.
        $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*/i', '$1=$2#', $html) ?? $html;

        return trim($html);
    }

    /** The replier's configured signature, sanitized, or '' when none is set. */
    private function signatureHtml(int $userId): string
    {
        $raw = trim((new SignatureModel())->forUser($userId));
        if ($raw === '') {
            return '';
        }
        return '<br><div class="nexus-signature">' . $this->sanitizeHtml($raw) . '</div>';
    }

    /**
     * Builds the quoted history appended below the reply: every prior message of
     * the conversation, newest first, as "El <fecha>, <remitente> escribió:" plus
     * a blockquote. Capped to keep the email a sensible size.
     */
    private function historyHtml(int $conversationId): string
    {
        $prev = $this->messages
            ->where('conversation_id', $conversationId)
            ->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')
            ->findAll(15);
        if ($prev === []) {
            return '';
        }

        $meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
                  7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];

        $blocks = '';
        foreach ($prev as $m) {
            $who  = trim((string) ($m['from_name'] ?? '')) ?: trim((string) ($m['from_email'] ?? '')) ?: 'Remitente';
            $mail = trim((string) ($m['from_email'] ?? ''));
            $ts   = strtotime((string) ($m['received_at'] ?? '')) ?: time();
            $when = date('j', $ts) . ' ' . ($meses[(int) date('n', $ts)] ?? '') . ' ' . date('Y', $ts) . ' ' . date('H:i', $ts);

            $header = 'El ' . esc($when) . ', ' . esc($who)
                . ($mail !== '' ? ' &lt;' . esc($mail) . '&gt;' : '') . ' escribió:';

            $bodyHtml = (int) ($m['body_is_html'] ?? 0) === 1 && trim((string) $m['body']) !== ''
                ? $this->sanitizeHtml((string) $m['body'])
                : nl2br(esc((string) ($m['body'] !== '' ? $m['body'] : ($m['body_preview'] ?? ''))));

            $blocks .= '<div style="margin-top:12px;">'
                . '<div style="color:#6b7885; font-size:12px;">' . $header . '</div>'
                . '<blockquote style="margin:6px 0 0; padding:0 0 0 12px; border-left:2px solid #d0d7de; color:#3c4a57;">'
                . $bodyHtml
                . '</blockquote></div>';
        }

        return '<br><div style="border-top:1px solid #e1e4e8; margin-top:16px; padding-top:8px;">' . $blocks . '</div>';
    }
}
