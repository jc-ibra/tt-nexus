<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;
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

    /**
     * @param array $files UploadedFile[] posted with the reply (already the
     *                     validated set, or raw — validated here defensively).
     */
    public function reply(int $conversationId, string $body, int $userId, array $files = []): ServiceResult
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

        // Validate attachments before sending.
        $vres = $this->attachments->validateUploads($files);
        if (! $vres->success) {
            return $vres;
        }
        $validFiles = (array) $vres->data;

        $target = $this->latestReplyTarget($conversationId);
        $html   = $this->toHtml($body);

        // Our own Message-ID so the re-synced sent copy can be de-duplicated.
        $fromEmail = $this->settings->smtpFromEmail();
        $domain    = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $messageId = '<nexus-' . bin2hex(random_bytes(12)) . '@' . $domain . '>';

        $subject = (string) ($conv['subject'] ?? '');
        if (stripos($subject, 're:') !== 0) {
            $subject = 'Re: ' . $subject;
        }

        $send = $this->send($to, $subject, $html, $messageId, $target, $validFiles);
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
            'from_name'           => $this->settings->smtpFromName(),
            'from_email'          => $fromEmail,
            'to_recipients'       => $to,
            'subject'             => $subject,
            'body_preview'        => mb_substr($body, 0, 255),
            'body'                => $html,
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
    private function send(string $to, string $subject, string $html, string $messageId, ?array $target, array $files = []): ServiceResult
    {
        $config = new EmailConfig();
        $config->protocol   = 'smtp';
        $config->SMTPHost   = $this->settings->smtpHost();
        $config->SMTPUser   = $this->settings->smtpUsername();
        $config->SMTPPass   = $this->settings->smtpPassword();
        $config->SMTPPort   = $this->settings->smtpPort();
        $config->SMTPCrypto = $this->settings->smtpEncryption() === 'none' ? '' : $this->settings->smtpEncryption();
        $config->mailType   = 'html';
        $config->charset    = 'UTF-8';
        $config->wordWrap   = true;

        $email = \Config\Services::email($config, false);
        $email->setFrom($this->settings->smtpFromEmail(), $this->settings->smtpFromName());
        $email->setTo($to);
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

        // Attach the uploaded files (read from their temp path).
        foreach ($files as $file) {
            if ($file instanceof \CodeIgniter\HTTP\Files\UploadedFile && $file->isValid()) {
                $email->attach($file->getTempName(), '', $file->getClientName(), $file->getClientMimeType() ?: '');
            }
        }

        if (! $email->send(false)) {
            log_message('error', '[SmtpReplyService] send failed: ' . $email->printDebugger(['headers']));
            return ServiceResult::fail('No se pudo enviar la respuesta por SMTP. Revisa las credenciales SMTP.');
        }

        return ServiceResult::ok();
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

    private function toHtml(string $text): string
    {
        return nl2br(esc($text));
    }
}
