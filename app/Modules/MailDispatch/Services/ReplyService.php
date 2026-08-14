<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;

/**
 * Phase 3: replies to a conversation from Nexus via Microsoft Graph. The reply
 * is sent as a reply to the latest message so it threads under the original,
 * from the shared mailbox address. The sent message is recorded immediately as
 * an outbound message and the conversation is marked "Respondida" without
 * waiting for the next sync.
 *
 * Gated by the send_from_nexus_enabled switch; when off, Nexus stays read-only.
 */
class ReplyService
{
    public function __construct(
        private MailDispatchSettings $settings,
        private GraphMailService $graph,
        private ConversationModel $conversations,
        private MessageModel $messages,
        private EventModel $events
    ) {}

    /** Máximo de copias manuales por respuesta (igual que en SMTP). */
    private const MAX_CC = 10;

    /**
     * @param string $cc Copias escritas a mano por el agente. Vacío por defecto:
     *                   la respuesta va solo al solicitante.
     */
    public function reply(int $conversationId, string $body, int $userId, array $files = [], string $cc = ''): ServiceResult
    {
        if (! $this->settings->isSendEnabled()) {
            return ServiceResult::fail('La respuesta desde Nexus está deshabilitada en la configuración.');
        }
        // Graph attachment sending is not implemented yet (phase B).
        if ($files !== []) {
            return ServiceResult::fail('El envío de adjuntos aún no está disponible en modo Microsoft Graph.');
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

        $ccRes = $this->parseCc($cc, (string) ($conv['requester_email'] ?? ''));
        if (! $ccRes->success) {
            return $ccRes;
        }
        $ccList = (array) $ccRes->data;

        // Reply under the most recent message of the thread (prefer inbound).
        $target = $this->latestReplyTarget($conversationId);
        if ($target === null) {
            return ServiceResult::fail('No se encontró un mensaje del hilo al cual responder.');
        }

        $html = $this->toHtml($body);
        $res  = $this->graph->reply($target['graph_id'], $html, $ccList);
        if (! $res['success']) {
            return ServiceResult::fail('No se pudo enviar la respuesta: ' . $res['error']);
        }

        $now = date('Y-m-d H:i:s');

        // Record the outbound message immediately (synthetic id; the real Sent
        // Items copy arrives on the next sync with its own Graph id).
        $this->messages->insert([
            'conversation_id' => $conversationId,
            'graph_id'        => 'nexus:' . bin2hex(random_bytes(10)),
            'direction'       => 'out',
            'from_name'       => 'Mesa de ayuda',
            'from_email'      => $this->settings->mailbox(),
            'to_recipients'   => (string) ($conv['requester_email'] ?? ''),
            'cc_recipients'   => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject'         => (string) ($conv['subject'] ?? ''),
            'body_preview'    => mb_substr($body, 0, 255),
            'body'            => $html,
            'body_is_html'    => 1,
            'has_attachments' => 0,
            'received_at'     => $now,
        ]);

        $fromStatus = (string) $conv['status'];
        $this->conversations->set('message_count', 'message_count + 1', false)
            ->where('id', $conversationId)->update();
        $this->conversations->update($conversationId, [
            'status'            => 'respondida',
            'first_response_at' => $conv['first_response_at'] ?: $now,
            'last_activity_at'  => $now,
        ]);

        $this->events->log($conversationId, 'status', $userId, $fromStatus, 'respondida', 'Respuesta enviada desde Nexus.');

        return ServiceResult::ok(null, 'Respuesta enviada al hilo.');
    }

    /**
     * Forwards one message of the thread to somebody else — the agent answered
     * and only then realised somebody who had to be notified was never copied.
     * Graph re-sends the stored original with its attachments, so the mail the
     * third party receives is the real one, not a rebuilt copy.
     *
     * A forward is not an answer to the requester: the conversation keeps its
     * status, its first response time and its last activity. Only the sent mail
     * is appended to the thread, as the record of who was notified.
     */
    public function forward(int $conversationId, int $messageId, string $to, string $cc, string $comment, int $userId): ServiceResult
    {
        if (! $this->settings->isSendEnabled()) {
            return ServiceResult::fail('El envío desde Nexus está deshabilitado en la configuración.');
        }

        $conv = $this->conversations->find($conversationId);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }

        $msg = $this->messages->where('id', $messageId)->where('conversation_id', $conversationId)->first();
        if ($msg === null) {
            return ServiceResult::fail('El mensaje no pertenece a esta conversación.');
        }

        // Graph forwards a message that lives in the mailbox. A reply sent from
        // Nexus is stored with a synthetic id until its Sent Items copy syncs
        // back, and until then Microsoft has nothing to forward.
        $graphId = (string) ($msg['graph_id'] ?? '');
        if ($graphId === '' || str_starts_with($graphId, 'nexus:')) {
            return ServiceResult::fail(
                'Este mensaje se envió desde Nexus y su copia aún no se sincroniza con el buzón. '
                . 'Espera a la siguiente sincronización para poder reenviarlo.'
            );
        }

        $toRes = $this->parseCc($to, '', 'destinatarios');
        if (! $toRes->success) {
            return $toRes;
        }
        $toList = (array) $toRes->data;
        if ($toList === []) {
            return ServiceResult::fail('Indica a quién quieres reenviar el mensaje.');
        }

        $ccRes = $this->parseCc($cc, '');
        if (! $ccRes->success) {
            return $ccRes;
        }
        $ccList = array_values(array_diff((array) $ccRes->data, $toList));

        $res = $this->graph->forward($graphId, $this->toHtml(trim($comment)), $toList, $ccList);
        if (! $res['success']) {
            return ServiceResult::fail('No se pudo reenviar el mensaje: ' . $res['error']);
        }

        $now      = date('Y-m-d H:i:s');
        $everyone = array_merge($toList, $ccList);

        $this->messages->insert([
            'conversation_id' => $conversationId,
            'graph_id'        => 'nexus:' . bin2hex(random_bytes(10)),
            'direction'       => 'out',
            'from_name'       => 'Mesa de ayuda',
            'from_email'      => $this->settings->mailbox(),
            'to_recipients'   => implode(', ', $toList),
            'cc_recipients'   => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject'         => 'RV: ' . (string) ($msg['subject'] ?? ''),
            'body_preview'    => mb_substr('Reenviado: ' . (string) ($msg['body_preview'] ?? ''), 0, 255),
            'body'            => (string) ($msg['body'] ?? ''),
            'body_is_html'    => (int) ($msg['body_is_html'] ?? 1),
            'has_attachments' => (int) ($msg['has_attachments'] ?? 0),
            'received_at'     => $now,
        ]);

        $this->conversations->set('message_count', 'message_count + 1', false)
            ->where('id', $conversationId)->update();

        $this->events->log($conversationId, 'forward', $userId, null, null, 'Mensaje reenviado a: ' . implode(', ', $everyone) . '.');

        return ServiceResult::ok(null, 'Mensaje reenviado a ' . implode(', ', $everyone) . '.');
    }

    /**
     * Parses the agent's manual Cc field into a clean address list. Copies are
     * never derived from the thread's own To/Cc: each one is typed on purpose,
     * so a busy thread does not multiply the mail we send.
     *
     * @return ServiceResult data = string[] of validated addresses
     */
    private function parseCc(string $raw, string $to, string $label = 'copia'): ServiceResult
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ServiceResult::ok([]);
        }

        $parts = preg_split('/[,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $valid   = [];
        $invalid = [];
        $toLower = strtolower(trim($to));
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
            return ServiceResult::fail('Correo inválido en ' . $label . ': ' . implode(', ', $invalid));
        }
        if (count($valid) > self::MAX_CC) {
            return ServiceResult::fail('Demasiados correos en ' . $label . ': el máximo es ' . self::MAX_CC . '.');
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
            ->where("graph_id NOT LIKE 'nexus:%'", null, false)
            ->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')
            ->first();
    }

    private function toHtml(string $text): string
    {
        return nl2br(esc($text));
    }
}
