<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;

/**
 * The heart of MailDispatch: turns synced Graph messages into threaded
 * conversations, and drives the state machine and assignment operations the
 * operational area exposes. All mutations are audited in maildispatch_events.
 *
 * State machine:
 *   nueva -> asignada -> en_atencion -> respondida <-> esperando_agente -> cerrada
 * A closed conversation that receives a new inbound message reopens into
 * esperando_agente, keeping its prior assignment.
 */
class ConversationService
{
    public function __construct(
        private ConversationModel $conversations,
        private MessageModel $messages,
        private EventModel $events,
        private DispositionModel $dispositions,
        private AgentModel $agents
    ) {}

    // =======================================================================
    // Ingestion (called by the sync)
    // =======================================================================

    /**
     * Ingests a single Graph message into the conversation model. Idempotent by
     * Graph message id. Returns 'created' | 'appended' | 'skipped'.
     */
    public function ingest(array $m, string $mailbox, string $folder = 'inbox'): string
    {
        $graphId = (string) ($m['id'] ?? '');
        if ($graphId === '') {
            return 'skipped';
        }
        if ($this->messages->existsByGraphId($graphId)) {
            return 'skipped';
        }
        // Drafts are unsent — ignore until they actually leave.
        if (! empty($m['isDraft'])) {
            return 'skipped';
        }
        // Dedupe by RFC Message-ID: the same mail can reappear under a different
        // backend id (e.g. an SMTP reply whose forwarded copy re-syncs via IMAP).
        $internetMessageId = trim((string) ($m['internetMessageId'] ?? ''));
        if ($internetMessageId !== '' && $this->messages->existsByInternetMessageId($internetMessageId)) {
            return 'skipped';
        }

        $fields    = $this->extract($m, $mailbox, $folder);
        $direction = $fields['direction'];

        // ---- Locate the conversation (thread) -----------------------------
        $conv = null;
        if ($fields['conversation_id'] !== '') {
            $conv = $this->conversations->findByGraphId($fields['conversation_id']);
        }
        if ($conv === null) {
            // Fallback: In-Reply-To / References -> a stored internetMessageId.
            $refConvId = $this->messages->conversationIdForReference(
                array_merge($fields['in_reply_to_ids'], $fields['reference_ids'])
            );
            if ($refConvId !== null) {
                $conv = $this->conversations->find($refConvId);
            }
        }

        if ($conv === null) {
            return $this->createConversation($fields, $graphId);
        }

        return $this->appendToConversation((int) $conv['id'], $conv, $fields, $graphId);
    }

    /** Creates a brand-new conversation from the first message we've seen. */
    private function createConversation(array $f, string $graphId): string
    {
        // A stable thread key is required (unique column). Fall back to the
        // internet message id when Graph gives no conversationId.
        $convKey = $f['conversation_id'] !== ''
            ? $f['conversation_id']
            : ($f['internet_message_id'] !== '' ? 'imid:' . $f['internet_message_id'] : 'graph:' . $graphId);

        $isOut = $f['direction'] === 'out';

        $convId = $this->conversations->insert([
            'conversation_id'   => $convKey,
            'mailbox_address'   => $f['mailbox'],
            'subject'           => $f['subject'],
            'requester_name'    => $isOut ? $f['to_name']  : $f['from_name'],
            'requester_email'   => $isOut ? $f['to_email'] : $f['from_email'],
            'status'            => $isOut ? 'respondida' : 'nueva',
            'message_count'     => 0,
            'received_at'       => $f['received_at'],
            'first_response_at' => $isOut ? $f['received_at'] : null,
            'last_activity_at'  => $f['received_at'],
        ], true);

        $this->insertMessage((int) $convId, $f, $graphId);
        $this->conversations->set('message_count', 'message_count + 1', false)
            ->where('id', $convId)->update();

        return 'created';
    }

    /** Appends a message to an existing conversation and advances its state. */
    private function appendToConversation(int $convId, array $conv, array $f, string $graphId): string
    {
        $this->insertMessage($convId, $f, $graphId);

        $set = [];

        // last_activity_at = most recent of the two.
        $set['last_activity_at'] = $this->maxDate($conv['last_activity_at'] ?? null, $f['received_at']);

        $status    = (string) $conv['status'];
        $newStatus = $status;
        $sysEvents = [];

        if ($f['direction'] === 'out') {
            // Agent answered (from Outlook). Record first response, mark answered.
            if (empty($conv['first_response_at'])) {
                $set['first_response_at'] = $f['received_at'];
            }
            if ($status !== 'cerrada' && $status !== 'respondida') {
                $newStatus = 'respondida';
                $sysEvents[] = ['status', $status, $newStatus];
            }
        } else {
            // Inbound customer message.
            if ($status === 'cerrada') {
                // Reopen keeping the prior assignment.
                $newStatus   = 'esperando_agente';
                $sysEvents[] = ['reopen', $status, $newStatus];
            } elseif (in_array($status, ['respondida', 'en_atencion', 'asignada'], true)) {
                $newStatus   = 'esperando_agente';
                $sysEvents[] = ['status', $status, $newStatus];
            }
        }

        if ($newStatus !== $status) {
            $set['status'] = $newStatus;
        }

        // Apply the scalar sets, then the raw counter increment.
        $this->conversations->set($set)
            ->set('message_count', 'message_count + 1', false)
            ->where('id', $convId)->update();

        // System-authored audit entries (no user).
        foreach ($sysEvents as [$type, $from, $to]) {
            $this->events->log($convId, $type, null, $from, $to, 'Automático por sincronización de correo.');
        }

        return 'appended';
    }

    private function insertMessage(int $convId, array $f, string $graphId): void
    {
        $this->messages->insert([
            'conversation_id'     => $convId,
            'graph_id'            => $graphId,
            'internet_message_id' => $f['internet_message_id'] ?: null,
            'in_reply_to'         => $f['in_reply_to_ids'][0] ?? null,
            'references_header'   => $f['references_raw'] ?: null,
            'direction'           => $f['direction'],
            'from_name'           => $f['from_name'],
            'from_email'          => $f['from_email'],
            'to_recipients'       => $f['to_recipients'],
            'subject'             => $f['subject'],
            'body_preview'        => $f['body_preview'],
            'body'                => $f['body'],
            'body_is_html'        => $f['body_is_html'],
            'has_attachments'     => $f['has_attachments'],
            'received_at'         => $f['received_at'],
        ]);
    }

    // =======================================================================
    // Operational actions
    // =======================================================================

    /**
     * Atomic claim: an agent takes an unassigned conversation. The UPDATE is
     * conditioned on agent_id still being NULL, so if two agents claim at once
     * only one wins; the loser gets a clear "ya fue tomada por X" message.
     */
    public function claim(int $id, int $userId): ServiceResult
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $db->table('maildispatch_conversations')
            ->where('id', $id)
            ->where('agent_id', null)
            ->where('status !=', 'cerrada')
            ->set('agent_id', $userId)
            ->set('assigned_at', "COALESCE(assigned_at, '{$now}')", false)
            ->set('status', "CASE WHEN status = 'nueva' THEN 'asignada' ELSE status END", false)
            ->set('updated_at', $now)
            ->update();

        if ($db->affectedRows() === 0) {
            $conv = $this->conversations->find($id);
            if ($conv === null) {
                return ServiceResult::fail('La conversación no existe.');
            }
            if ((string) $conv['status'] === 'cerrada') {
                return ServiceResult::fail('La conversación ya está cerrada.');
            }
            $who = $this->agentName((int) $conv['agent_id']);
            return ServiceResult::fail("Ya fue tomada por {$who}.");
        }

        $this->events->log($id, 'assign', $userId, null, (string) $userId, 'Tomada por el agente.');
        return ServiceResult::ok(null, 'Conversación asignada a ti.');
    }

    /**
     * Dispatcher assigns / reassigns to any agent (or unassigns when target=0).
     */
    public function assign(int $id, int $targetUserId, int $actorId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        if ((string) $conv['status'] === 'cerrada') {
            return ServiceResult::fail('No puedes reasignar una conversación cerrada; reábrela primero.');
        }

        $from = $conv['agent_id'] !== null ? (string) $conv['agent_id'] : null;
        $now  = date('Y-m-d H:i:s');

        if ($targetUserId <= 0) {
            // Unassign.
            $newStatus = (string) $conv['status'] === 'asignada' ? 'nueva' : (string) $conv['status'];
            $this->conversations->update($id, [
                'agent_id' => null,
                'status'   => $newStatus,
            ]);
            $this->events->log($id, 'unassign', $actorId, $from, null, 'Conversación liberada.');
            return ServiceResult::ok(null, 'Conversación liberada.');
        }

        if (! $this->agents->isAgent($targetUserId)) {
            return ServiceResult::fail('El destinatario no es un agente de despacho activo.');
        }

        $this->conversations->update($id, [
            'agent_id'    => $targetUserId,
            'assigned_at' => $conv['assigned_at'] ?: $now,
            'status'      => (string) $conv['status'] === 'nueva' ? 'asignada' : (string) $conv['status'],
        ]);

        $type = $from === null ? 'assign' : 'reassign';
        $this->events->log($id, $type, $actorId, $from, (string) $targetUserId, 'Asignada por dispatcher.');

        return ServiceResult::ok(null, 'Conversación asignada a ' . $this->agentName($targetUserId) . '.');
    }

    /** Manual state change from the detail view. */
    public function changeStatus(int $id, string $status, int $userId): ServiceResult
    {
        $allowed = (new \App\Modules\MailDispatch\Config\MailDispatch())->manualStatuses;
        if (! in_array($status, $allowed, true)) {
            return ServiceResult::fail('Estado no válido.');
        }
        $conv = $this->conversations->find($id);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        if ((string) $conv['status'] === 'cerrada') {
            return ServiceResult::fail('La conversación está cerrada. Reábrela para cambiar su estado.');
        }

        $data = ['status' => $status];
        if ($status === 'respondida' && empty($conv['first_response_at'])) {
            $data['first_response_at'] = date('Y-m-d H:i:s');
        }
        $this->conversations->update($id, $data);
        $this->events->log($id, 'status', $userId, (string) $conv['status'], $status);

        return ServiceResult::ok(null, 'Estado actualizado.');
    }

    /** Closes a conversation; requires a disposition (and folio when demanded). */
    public function close(int $id, int $dispositionId, ?string $folio, ?string $comment, int $userId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        $disp = $this->dispositions->find($dispositionId);
        if ($disp === null || (int) $disp['is_active'] !== 1) {
            return ServiceResult::fail('Selecciona una disposición de cierre válida.');
        }
        $folio = trim((string) $folio);
        if ((int) $disp['requires_folio'] === 1 && $folio === '') {
            return ServiceResult::fail('La disposición «' . $disp['name'] . '» requiere el número de folio GLPI.');
        }

        $this->conversations->update($id, [
            'status'         => 'cerrada',
            'disposition_id' => $dispositionId,
            'glpi_folio'     => $folio !== '' ? $folio : null,
            'close_comment'  => ($comment !== null && trim($comment) !== '') ? trim($comment) : null,
            'closed_at'      => date('Y-m-d H:i:s'),
        ]);
        $this->events->log($id, 'close', $userId, (string) $conv['status'], $disp['name'], $folio !== '' ? "Folio: {$folio}" : null);

        return ServiceResult::ok(null, 'Conversación cerrada.');
    }

    /** Reopens a closed conversation into esperando_agente, keeping its agent. */
    public function reopen(int $id, int $userId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        if ((string) $conv['status'] !== 'cerrada') {
            return ServiceResult::fail('La conversación no está cerrada.');
        }
        $newStatus = $conv['agent_id'] !== null ? 'esperando_agente' : 'nueva';
        $this->conversations->update($id, [
            'status'    => $newStatus,
            'closed_at' => null,
        ]);
        $this->events->log($id, 'reopen', $userId, 'cerrada', $newStatus);

        return ServiceResult::ok(null, 'Conversación reabierta.');
    }

    /** Adds an internal note (Nexus-only, not sent to the requester). */
    public function addNote(int $id, string $note, int $userId): ServiceResult
    {
        $note = trim($note);
        if ($note === '') {
            return ServiceResult::fail('La nota está vacía.');
        }
        if ($this->conversations->find($id) === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        $this->events->log($id, 'note', $userId, null, null, $note);
        return ServiceResult::ok(null, 'Nota agregada.');
    }

    // =======================================================================
    // Helpers
    // =======================================================================

    /** Normalizes a Graph message into the flat shape our tables expect. */
    private function extract(array $m, string $mailbox, string $folder): array
    {
        $fromAddr = $m['from']['emailAddress'] ?? ($m['sender']['emailAddress'] ?? []);
        $fromEmail = strtolower(trim((string) ($fromAddr['address'] ?? '')));
        $fromName  = trim((string) ($fromAddr['name'] ?? ''));

        $toList = [];
        $toName = '';
        $toEmail = '';
        foreach ((array) ($m['toRecipients'] ?? []) as $i => $r) {
            $addr = $r['emailAddress']['address'] ?? '';
            $nm   = $r['emailAddress']['name'] ?? '';
            if ($addr !== '') {
                $toList[] = $addr;
                if ($i === 0) {
                    $toEmail = strtolower(trim((string) $addr));
                    $toName  = trim((string) $nm);
                }
            }
        }

        $mailboxLower = strtolower(trim($mailbox));
        $direction = ($folder === 'sentitems' || ($fromEmail !== '' && $fromEmail === $mailboxLower)) ? 'out' : 'in';

        // Threading headers.
        $inReplyTo = [];
        $refs      = [];
        $refsRaw   = '';
        foreach ((array) ($m['internetMessageHeaders'] ?? []) as $h) {
            $name = strtolower((string) ($h['name'] ?? ''));
            $val  = (string) ($h['value'] ?? '');
            if ($name === 'in-reply-to') {
                $inReplyTo = array_merge($inReplyTo, $this->parseMessageIds($val));
            } elseif ($name === 'references') {
                $refsRaw = $val;
                $refs    = array_merge($refs, $this->parseMessageIds($val));
            }
        }

        $body        = $m['body']['content'] ?? '';
        $bodyType    = strtolower((string) ($m['body']['contentType'] ?? 'html'));

        return [
            'mailbox'             => $mailbox,
            'conversation_id'     => (string) ($m['conversationId'] ?? ''),
            'internet_message_id' => trim((string) ($m['internetMessageId'] ?? '')),
            'in_reply_to_ids'     => $inReplyTo,
            'reference_ids'       => $refs,
            'references_raw'      => $refsRaw,
            'direction'           => $direction,
            'from_name'           => $fromName ?: $fromEmail,
            'from_email'          => $fromEmail,
            'to_name'             => $toName ?: $toEmail,
            'to_email'            => $toEmail,
            'to_recipients'       => implode(', ', $toList),
            'subject'             => trim((string) ($m['subject'] ?? '(sin asunto)')),
            'body_preview'        => trim((string) ($m['bodyPreview'] ?? '')),
            'body'                => (string) $body,
            'body_is_html'        => $bodyType === 'html' ? 1 : 0,
            'has_attachments'     => ! empty($m['hasAttachments']) ? 1 : 0,
            'received_at'         => $this->parseDate((string) ($m['receivedDateTime'] ?? '')),
        ];
    }

    /** Extracts <...> message-id tokens from an In-Reply-To/References header. */
    private function parseMessageIds(string $value): array
    {
        if ($value === '') {
            return [];
        }
        if (preg_match_all('/<[^>]+>/', $value, $mm) && ! empty($mm[0])) {
            return array_map('trim', $mm[0]);
        }
        return [trim($value)];
    }

    private function parseDate(string $iso): ?string
    {
        if ($iso === '') {
            return null;
        }
        $ts = strtotime($iso);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function maxDate(?string $a, ?string $b): ?string
    {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return strtotime($b) >= strtotime($a) ? $b : $a;
    }

    private function agentName(?int $userId): string
    {
        if (! $userId) {
            return 'otro agente';
        }
        $row = \Config\Database::connect()->table('core_users')->select('name')->where('id', $userId)->get()->getRow();
        return $row ? (string) $row->name : 'otro agente';
    }
}
