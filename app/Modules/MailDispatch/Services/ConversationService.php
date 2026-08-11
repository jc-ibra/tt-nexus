<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;
use App\Modules\MailDispatch\Models\MessageRefModel;
use App\Modules\MailDispatch\Models\RuleModel;

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
        private AgentModel $agents,
        private AttachmentService $attachments,
        private MessageRefModel $messageRefs,
        private MailDispatchSettings $settings,
        private RuleModel $rules,
        private AutogenMatcher $autogen
    ) {}

    /** Lazily-loaded active auto-triage rules (cached for the sync batch). */
    private ?array $activeRules = null;

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
        // Import cutoff: drop anything older than sync_since. The provider filters
        // already bound the pull server-side; this enforces the exact time-of-day
        // (IMAP SINCE is day-granular) and guards providers that ignore the filter.
        $cutoff = $this->settings->syncSinceTimestamp();
        if ($cutoff !== null) {
            $received = strtotime((string) ($m['receivedDateTime'] ?? ''));
            if ($received !== false && $received < $cutoff) {
                return 'skipped';
            }
        }
        // Dedupe by RFC Message-ID: the same mail can reappear under a different
        // backend id (e.g. an SMTP reply whose forwarded copy re-syncs via IMAP).
        $internetMessageId = trim((string) ($m['internetMessageId'] ?? ''));
        if ($internetMessageId !== '' && $this->messages->existsByInternetMessageId($internetMessageId)) {
            return 'skipped';
        }

        $fields    = $this->extract($m, $mailbox, $folder);
        $direction = $fields['direction'];

        // Second dedupe pass: the same mail delivered twice. Happens when two of
        // its recipients redirect into this mailbox — each copy carries its own
        // Message-ID, so the check above lets both through and the thread (or a
        // whole duplicate conversation) ends up doubled. Runs after extract() so
        // forward mode's rewritten sender/subject are the ones compared.
        if ($this->messages->existsDuplicateDelivery(
            (string) $fields['from_email'],
            (string) $fields['subject'],
            $fields['received_at']
        )) {
            return 'skipped';
        }

        // ---- Locate the conversation (thread) -----------------------------
        $conv = null;
        if ($fields['conversation_id'] !== '') {
            $conv = $this->conversations->findByGraphId($fields['conversation_id']);
        }
        if ($conv === null) {
            // Reference-overlap: any of this message's tokens (own Message-ID,
            // In-Reply-To, References) already stored against another message.
            // Threads replies AND forwarded chains that share ancestor ids.
            $refConvId = $this->messageRefs->conversationByTokens($this->threadTokens($fields));
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

        // Auto-triage entrante: primero Autogestión (crear ticket GLPI), luego
        // Autoarchivo (mover fuera de la bandeja). Cada uno deja la conversación
        // en su propio bucket, verificable por un agente.
        $status  = $isOut ? 'respondida' : 'nueva';
        $rule    = null;
        $autogen = null;
        if (! $isOut) {
            $autogen = $this->autogen->match($f);
            if ($autogen !== null) {
                $status = 'autogenerado';
            } else {
                $rule = $this->matchRule((string) $f['from_email'], (string) $f['subject']);
                if ($rule !== null) {
                    $status = 'autoarchivo';
                }
            }
        }

        $data = [
            'conversation_id'   => $convKey,
            'mailbox_address'   => $f['mailbox'],
            'subject'           => $f['subject'],
            // For an outbound-initiated thread the counterpart is the first To.
            // It is stored because a reply needs a destination, but the thread
            // has no requester yet — outbound_only says so, and the UI must not
            // present that address as the person who wrote in.
            'requester_name'    => $isOut ? $f['to_name']  : $f['from_name'],
            'requester_email'   => $isOut ? $f['to_email'] : $f['from_email'],
            'outbound_only'     => $isOut ? 1 : 0,
            'status'            => $status,
            'auto_rule_id'      => $rule['id'] ?? null,
            'message_count'     => 0,
            'received_at'       => $f['received_at'],
            'first_response_at' => $isOut ? $f['received_at'] : null,
            'last_activity_at'  => $f['received_at'],
        ];
        if ($autogen !== null) {
            $data['autogen_rule_id'] = (int) $autogen['rule']['id'];
            $data['autogen_state']   = $autogen['state'];
            $data['autogen_payload'] = json_encode($autogen['payload'], JSON_UNESCAPED_UNICODE);
        }
        $convId = $this->conversations->insert($data, true);

        $this->insertMessage((int) $convId, $f, $graphId);
        $this->conversations->set('message_count', 'message_count + 1', false)
            ->where('id', $convId)->update();

        if ($autogen !== null) {
            $note = 'Regla: ' . $autogen['rule']['name'] . ($autogen['state'] === 'review' ? ' — ' . (string) $autogen['note'] : '');
            $this->events->log((int) $convId, 'autogen', null, 'nueva', 'autogenerado', $note);
        } elseif ($rule !== null) {
            $this->events->log((int) $convId, 'autoclose', null, 'nueva', 'autoarchivo', 'Regla: ' . $rule['name']);
        }

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
            // Inbound customer message. If the thread was started by the help
            // desk, someone just answered it: it stops being a broadcast and
            // rejoins the work queue, with the real sender as its requester.
            if (! empty($conv['outbound_only'])) {
                $set['outbound_only']   = 0;
                $set['requester_name']  = $f['from_name'];
                $set['requester_email'] = $f['from_email'];
            }
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

    private function insertMessage(int $convId, array $f, string $graphId): int
    {
        $messageId = (int) $this->messages->insert([
            'conversation_id'     => $convId,
            'graph_id'            => $graphId,
            'internet_message_id' => $f['internet_message_id'] ?: null,
            'in_reply_to'         => $f['in_reply_to_ids'][0] ?? null,
            'references_header'   => $f['references_raw'] ?: null,
            'direction'           => $f['direction'],
            'from_name'           => $f['from_name'],
            'from_email'          => $f['from_email'],
            'to_recipients'       => $f['to_recipients'],
            'cc_recipients'       => $f['cc_recipients'] ?: null,
            'subject'             => $f['subject'],
            'body_preview'        => $f['body_preview'],
            'body'                => $f['body'],
            'body_is_html'        => $f['body_is_html'],
            'has_attachments'     => $f['has_attachments'],
            'received_at'         => $f['received_at'],
        ], true);

        // Persist the message's attachments (files + metadata rows).
        if ($messageId > 0 && ! empty($f['attachments'])) {
            $this->attachments->storeIncoming($messageId, $convId, $f['attachments']);
        }

        // Persist the message's thread tokens for reference-overlap matching.
        if ($messageId > 0) {
            $this->messageRefs->storeTokens($messageId, $this->threadTokens($f));
        }

        return $messageId;
    }

    /** All RFC message-id tokens a message carries: own id + In-Reply-To + References. */
    private function threadTokens(array $f): array
    {
        $own = trim((string) ($f['internet_message_id'] ?? ''));
        return array_merge(
            $own !== '' ? [$own] : [],
            $f['in_reply_to_ids'] ?? [],
            $f['reference_ids'] ?? []
        );
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

    // =======================================================================
    // Autoarchivo (auto-triaged bucket)
    // =======================================================================

    /**
     * Marks an auto-triaged conversation as verified by an agent. It stays in the
     * "autoarchivo" bucket (now flagged verified); this only records the sign-off.
     */
    public function verify(int $id, int $userId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        if ((string) $conv['status'] !== 'autoarchivo') {
            return ServiceResult::fail('Solo se pueden verificar conversaciones en autoarchivo.');
        }
        if (! empty($conv['verified_at'])) {
            return ServiceResult::fail('Esta conversación ya fue verificada.');
        }

        $this->conversations->update($id, [
            'verified_by' => $userId,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->events->log($id, 'verify', $userId, null, null, 'Autoarchivo verificado por el agente.');

        return ServiceResult::ok(null, 'Conversación verificada.');
    }

    /**
     * Moves an auto-triaged conversation back into the normal inbox flow (status
     * "nueva"), clearing the verification flag so it follows the standard cycle.
     */
    public function moveToInbox(int $id, int $userId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null) {
            return ServiceResult::fail('La conversación no existe.');
        }
        if ((string) $conv['status'] !== 'autoarchivo') {
            return ServiceResult::fail('Solo aplica a conversaciones en autoarchivo.');
        }

        $this->conversations->update($id, [
            'status'      => 'nueva',
            'verified_by' => null,
            'verified_at' => null,
        ]);
        $this->events->log($id, 'status', $userId, 'autoarchivo', 'nueva', 'Movida a la bandeja de entrada.');

        return ServiceResult::ok(null, 'Conversación movida a la bandeja.');
    }

    /**
     * Returns the first active rule matching the given sender/subject, or null.
     */
    private function matchRule(string $fromEmail, string $subject): ?array
    {
        if ($this->activeRules === null) {
            $this->activeRules = $this->rules->active();
        }
        foreach ($this->activeRules as $rule) {
            if ($this->ruleMatches($rule, $fromEmail, $subject)) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Whether a single rule matches a sender/subject. A rule matches when every
     * non-empty pattern it defines matches; a rule with both patterns empty never
     * matches (so it can't auto-triage everything by accident). A sender pattern
     * starting with "@" matches by domain; otherwise substring.
     */
    private function ruleMatches(array $rule, string $fromEmail, string $subject): bool
    {
        $sp = mb_strtolower(trim((string) ($rule['sender_pattern'] ?? '')));
        $tp = mb_strtolower(trim((string) ($rule['subject_pattern'] ?? '')));
        if ($sp === '' && $tp === '') {
            return false;
        }

        $email = mb_strtolower(trim($fromEmail));
        $subj  = mb_strtolower(trim($subject));

        if ($sp !== '') {
            $senderOk = $sp[0] === '@'
                ? str_ends_with($email, $sp)                 // domain match
                : ($email !== '' && str_contains($email, $sp));
            if (! $senderOk) {
                return false;
            }
        }
        if ($tp !== '' && ! str_contains($subj, $tp)) {
            return false;
        }

        return true;
    }

    /**
     * Retroactively applies one autoarchivo rule to the main inbox: every
     * conversation that is not yet taken (no agent) and not yet processed (not
     * cerrada/autoarchivo/autogenerado) and matches the rule is moved to the
     * "autoarchivo" bucket, tagged with the rule and audited. Lets an admin clear
     * the backlog for a rule created after the fact. Returns the count moved.
     */
    public function applyRuleToInbox(int $ruleId, int $userId): ServiceResult
    {
        $rule = $this->rules->find($ruleId);
        if ($rule === null) {
            return ServiceResult::fail('La regla no existe. Guarda la regla antes de aplicarla.');
        }
        if (trim((string) ($rule['sender_pattern'] ?? '')) === '' && trim((string) ($rule['subject_pattern'] ?? '')) === '') {
            return ServiceResult::fail('La regla no tiene patrones; no se puede aplicar.');
        }

        // Candidates = the unassigned main-inbox set (not taken, not processed).
        $candidates = $this->conversations
            ->where('agent_id', null)
            ->whereNotIn('status', ['cerrada', 'autoarchivo', 'autogenerado'])
            ->findAll();

        $moved = 0;
        foreach ($candidates as $c) {
            if (! $this->ruleMatches($rule, (string) ($c['requester_email'] ?? ''), (string) ($c['subject'] ?? ''))) {
                continue;
            }
            $from = (string) $c['status'];
            $this->conversations->update((int) $c['id'], [
                'status'       => 'autoarchivo',
                'auto_rule_id' => (int) $rule['id'],
            ]);
            $this->events->log((int) $c['id'], 'autoclose', $userId, $from, 'autoarchivo', 'Regla aplicada a la bandeja: ' . $rule['name']);
            $moved++;
        }

        return ServiceResult::ok(
            ['moved' => $moved],
            $moved === 0
                ? 'Ninguna conversación sin asignar de la bandeja coincidió con la regla.'
                : "Se archivaron {$moved} conversación(es) con la regla «{$rule['name']}»."
        );
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

        $ccList = [];
        foreach ((array) ($m['ccRecipients'] ?? []) as $r) {
            $addr = trim((string) ($r['emailAddress']['address'] ?? ''));
            if ($addr !== '') {
                $ccList[] = $addr;
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

        $subject     = trim((string) ($m['subject'] ?? '(sin asunto)'));
        $bodyPreview = trim((string) ($m['bodyPreview'] ?? ''));

        // Forward mode: the mailbox receives forwarded copies, so the SMTP sender
        // is the forwarder. Use the original sender from the body's De:/From:
        // block as the real requester/sender (and recompute direction), strip the
        // forwarding noise (RV:/[External]/…) from the subject, and take the
        // preview from the actual content (past the forwarded header block).
        if ($this->settings->treatAsForwards()) {
            $orig = ForwardParser::originalSender((string) $body);
            if ($orig !== null) {
                $fromName  = $orig['name'];
                $fromEmail = $orig['email'];
                $direction = $fromEmail === $mailboxLower ? 'out' : 'in';
            }
            $subject     = ForwardParser::cleanSubject($subject);
            $bodyPreview = ForwardParser::contentSnippet((string) $body);
        }

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
            'cc_recipients'       => implode(', ', $ccList),
            'subject'             => $subject,
            'body_preview'        => $bodyPreview,
            'body'                => (string) $body,
            'body_is_html'        => $bodyType === 'html' ? 1 : 0,
            'has_attachments'     => ! empty($m['hasAttachments']) ? 1 : 0,
            'attachments'         => is_array($m['attachments'] ?? null) ? $m['attachments'] : [],
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
