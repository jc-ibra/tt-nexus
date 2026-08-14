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
            'body_preview'        => ForwardParser::plainText($replyHtml, 255),
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
     * Forwards one message of the thread to somebody else. Written for the case
     * an agent answers and only then realises somebody who had to be notified
     * was never copied: instead of composing the mail again from Outlook, the
     * message already in the thread is re-sent, attachments included.
     *
     * A forward is not an answer to the requester, so it deliberately leaves the
     * conversation state alone — no status change, no first_response_at, no
     * last_activity_at. It only appends the sent mail to the thread (so there is
     * a record of who was notified) and audits it.
     *
     * @param string $to      Destinatarios, separados por coma o punto y coma.
     * @param string $cc      Copias opcionales.
     * @param string $comment Nota opcional del agente, arriba del mensaje reenviado.
     */
    public function forward(int $conversationId, int $messageId, string $to, string $cc, string $comment, int $userId): ServiceResult
    {
        if (! $this->settings->isSendEnabled()) {
            return ServiceResult::fail('El envío desde Nexus está deshabilitado o el SMTP no está configurado.');
        }

        $conv = $this->conversations->find($conversationId);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }

        $msg = $this->messages->where('id', $messageId)->where('conversation_id', $conversationId)->first();
        if ($msg === null) {
            return ServiceResult::fail('El mensaje no pertenece a esta conversación.');
        }

        $toRes = $this->parseList($to, '', 'destinatarios', 'Demasiados destinatarios: el máximo es ' . self::MAX_CC . ' por reenvío.');
        if (! $toRes->success) {
            return $toRes;
        }
        $toList = (array) $toRes->data;
        if ($toList === []) {
            return ServiceResult::fail('Indica a quién quieres reenviar el mensaje.');
        }

        $ccRes = $this->parseList($cc, '', 'copia', 'Demasiadas copias: el máximo es ' . self::MAX_CC . ' por reenvío.');
        if (! $ccRes->success) {
            return $ccRes;
        }
        // Nobody should get the same mail twice because they were typed in both fields.
        $ccList = array_values(array_diff((array) $ccRes->data, $toList));

        $filesRes = $this->attachments->readForResend($messageId);
        if (! $filesRes->success) {
            return $filesRes;
        }
        $files = (array) $filesRes->data;

        $subject = $this->forwardSubject((string) ($msg['subject'] ?? ($conv['subject'] ?? '')));

        $smtp      = $this->settings->effectiveSmtp();
        $fromEmail = $smtp['from_email'];
        $domain    = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $rfcMessageId = '<nexus-' . bin2hex(random_bytes(12)) . '@' . $domain . '>';

        $bodyHtml = $this->forwardHtml($msg, $comment);

        $send = $this->sendForward($smtp, $toList, $ccList, $subject, $bodyHtml, $rfcMessageId, $files);
        if (! $send->success) {
            return $send;
        }

        $now = date('Y-m-d H:i:s');

        // The sent mail joins the thread so the agent can see who was notified.
        // Its files are not copied to disk again: they are the very attachments
        // already stored under the message that was forwarded.
        $this->messages->insert([
            'conversation_id'     => $conversationId,
            'graph_id'            => 'nexus:' . bin2hex(random_bytes(10)),
            'internet_message_id' => $rfcMessageId,
            'direction'           => 'out',
            'from_name'           => $smtp['from_name'],
            'from_email'          => $fromEmail,
            'to_recipients'       => implode(', ', $toList),
            'cc_recipients'       => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject'             => $subject,
            'body_preview'        => ForwardParser::plainText($bodyHtml, 255),
            'body'                => $bodyHtml,
            'body_is_html'        => 1,
            'has_attachments'     => $files !== [] ? 1 : 0,
            'received_at'         => $now,
        ]);

        $this->conversations->set('message_count', 'message_count + 1', false)
            ->where('id', $conversationId)->update();

        $everyone = array_merge($toList, $ccList);
        $this->events->log(
            $conversationId,
            'forward',
            $userId,
            null,
            null,
            'Mensaje reenviado a: ' . implode(', ', $everyone) . '.'
        );

        return ServiceResult::ok(null, 'Mensaje reenviado a ' . implode(', ', $everyone) . '.');
    }

    /** "RV: <asunto>", without stacking a second prefix on an already-forwarded one. */
    private function forwardSubject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return 'RV: (sin asunto)';
        }
        foreach (['rv:', 'fw:', 'fwd:'] as $prefix) {
            if (stripos($subject, $prefix) === 0) {
                return $subject;
            }
        }

        return 'RV: ' . $subject;
    }

    /**
     * The forwarded mail's body: the agent's optional note, then the standard
     * "mensaje reenviado" header block, then the original message.
     */
    private function forwardHtml(array $msg, string $comment): string
    {
        $note = trim($comment) !== '' ? $this->normalizeBody($comment) . '<br>' : '';

        $rows = [
            'De'     => $this->senderLine($msg),
            'Fecha'  => $this->spanishDate((string) ($msg['received_at'] ?? '')),
            'Asunto' => (string) ($msg['subject'] ?? ''),
            'Para'   => (string) ($msg['to_recipients'] ?? ''),
            'CC'     => (string) ($msg['cc_recipients'] ?? ''),
        ];

        $head = '';
        foreach ($rows as $k => $v) {
            $v = trim($v);
            if ($v === '') {
                continue;
            }
            $head .= '<div><strong>' . esc($k) . ':</strong> ' . esc($v) . '</div>';
        }

        $body = (int) ($msg['body_is_html'] ?? 0) === 1 && trim((string) $msg['body']) !== ''
            ? $this->sanitizeHtml((string) $msg['body'])
            : nl2br(esc((string) (trim((string) ($msg['body'] ?? '')) !== '' ? $msg['body'] : ($msg['body_preview'] ?? ''))));

        return $note
            . '<div style="border-top:1px solid #e1e4e8; margin-top:16px; padding-top:8px;">'
            . '<div style="color:#6b7885; font-size:12px; margin-bottom:6px;">---------- Mensaje reenviado ----------</div>'
            . '<div style="font-size:12px; color:#3c4a57;">' . $head . '</div>'
            . '<div style="margin-top:12px;">' . $body . '</div>'
            . '</div>';
    }

    /** "Nombre <correo>", collapsing to whichever half the message actually has. */
    private function senderLine(array $msg): string
    {
        $name  = trim((string) ($msg['from_name'] ?? ''));
        $email = trim((string) ($msg['from_email'] ?? ''));

        if ($email === '') {
            return $name;
        }
        if ($name === '' || strcasecmp($name, $email) === 0) {
            return $email;
        }

        return $name . ' <' . $email . '>';
    }

    /** "14 ago 2026 12:33" for the forwarded header block. */
    private function spanishDate(string $raw): string
    {
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        $meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
                  7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];

        return date('j', $ts) . ' ' . ($meses[(int) date('n', $ts)] ?? '') . ' ' . date('Y', $ts) . ' ' . date('H:i', $ts);
    }

    /**
     * Sends the forwarded mail. Unlike a reply it carries no threading headers:
     * it is a new mail to a different audience, and threading it under the
     * customer's conversation would drop it into the wrong mailbox thread.
     *
     * Inline parts keep working: each one is attached as 'inline' and the cid:
     * reference in the body is rewritten to the Content-ID the mailer assigns,
     * so a signature logo travels instead of breaking.
     *
     * @param array<int,array{name:string,mime:string,content:string,inline:bool,content_id:string}> $files
     */
    private function sendForward(array $smtp, array $to, array $cc, string $subject, string $html, string $messageId, array $files): ServiceResult
    {
        $email = $this->mailer($smtp);
        $email->setFrom($smtp['from_email'], $smtp['from_name']);
        $email->setTo(implode(',', $to));
        if ($cc !== []) {
            $email->setCC(implode(',', $cc));
        }
        $email->setSubject($subject);
        $email->setHeader('Message-ID', $messageId);

        foreach ($files as $f) {
            $email->attach($f['content'], $f['inline'] ? 'inline' : 'attachment', $f['name'], $f['mime']);

            if ($f['inline'] && $f['content_id'] !== '') {
                $cid = $email->setAttachmentCID($f['name']);
                if (is_string($cid) && $cid !== '') {
                    $html = str_ireplace(
                        ['cid:<' . $f['content_id'] . '>', 'cid:' . $f['content_id']],
                        'cid:' . $cid,
                        $html
                    );
                }
            }
        }

        // Set after the rewrite so the body carries the final Content-IDs.
        $email->setMessage($html);

        if (! $email->send(false)) {
            log_message('error', '[SmtpReplyService] forward failed: ' . $email->printDebugger(['headers']));
            return ServiceResult::fail('No se pudo reenviar el mensaje por SMTP. Revisa las credenciales SMTP.');
        }

        return ServiceResult::ok();
    }

    /** A fresh Email instance configured with the effective SMTP credentials. */
    private function mailer(array $smtp): \CodeIgniter\Email\Email
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

        return \Config\Services::email($config, false);
    }

    /**
     * Sends the reply over SMTP with the stored credentials and threading
     * headers. Returns a ServiceResult; the SMTP debug output is logged, never
     * surfaced verbatim to the agent.
     */
    private function send(array $smtp, string $to, string $subject, string $html, string $messageId, ?array $target, array $files = [], array $cc = []): ServiceResult
    {
        $email = $this->mailer($smtp);
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
        return $this->parseList(
            $raw,
            $to,
            'copia',
            'Demasiadas copias: el máximo es ' . self::MAX_CC . ' por respuesta.'
        );
    }

    /**
     * Shared address-list parser: splits, validates, lowercases and de-duplicates,
     * dropping `$exclude` (the address that already goes as the main recipient).
     *
     * @return ServiceResult data = string[] of validated addresses
     */
    private function parseList(string $raw, string $exclude, string $label, string $overflowMessage): ServiceResult
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ServiceResult::ok([]);
        }

        $parts = preg_split('/[,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $valid        = [];
        $invalid      = [];
        $excludeLower = strtolower(trim($exclude));
        foreach ($parts as $part) {
            $addr = strtolower(trim($part, " \t\n\r\0\x0B<>"));
            if (! filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $part;
                continue;
            }
            if ($excludeLower !== '' && $addr === $excludeLower) {
                continue; // ya va como destinatario principal
            }
            $valid[$addr] = $addr;
        }

        if ($invalid !== []) {
            return ServiceResult::fail('Correo inválido en ' . $label . ': ' . implode(', ', $invalid));
        }
        if (count($valid) > self::MAX_CC) {
            return ServiceResult::fail($overflowMessage);
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
