<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\HelpdeskSupervisor\Models\LiveDeviationModel;
use App\Modules\HelpdeskSupervisor\Models\LiveTicketStateModel;
use App\Modules\HelpdeskSupervisor\Rules\AuditContext;
use App\Modules\HelpdeskSupervisor\Rules\RuleRegistry;

/**
 * Re-evaluates one GLPI ticket after a webhook (prefer update) and upserts
 * open/resolved rows in the live deviations table — never touches audit_runs.
 */
class LiveTicketAuditService
{
    /** Rules that make sense right after an agent fills/updates a ticket. */
    public const LIVE_RULE_KEYS = [
        'title_format',
        'reclassification',
        'field_completeness',
        'opening_date_default',
        'coordinator_assignment',
        'correct_tab',
        'ids_tab',
        'external_id',
    ];

    public function __construct(
        private HelpdeskSupervisorSettings $settings,
        private GlpiAuditQueryService $query,
        private AuditRunnerService $runner,
        private RuleRegistry $rules,
        private LiveDeviationModel $live,
        private LiveTicketStateModel $state,
    ) {}

    /**
     * @param array<string,mixed> $payload decoded GLPI webhook body
     */
    public function handleWebhookPayload(array $payload): ServiceResult
    {
        if (! $this->settings->webhookEnabled()) {
            return ServiceResult::fail('Recepción de webhooks desactivada.');
        }
        if (! $this->query->isConfigured()) {
            return ServiceResult::fail('Conexión GLPI no configurada.');
        }

        $event = $this->normalizeEvent((string) ($payload['event'] ?? $payload['Event'] ?? ''));
        if ($event === 'create' && ! $this->settings->webhookListenCreate()) {
            return ServiceResult::ok(['skipped' => true, 'reason' => 'create_disabled'], 'Evento create ignorado.');
        }
        if ($event === 'update' && ! $this->settings->webhookListenUpdate()) {
            return ServiceResult::ok(['skipped' => true, 'reason' => 'update_disabled'], 'Evento update ignorado.');
        }
        if ($event === '') {
            // Payload without event: treat as update (default GLPI may omit).
            $event = 'update';
            if (! $this->settings->webhookListenUpdate()) {
                return ServiceResult::ok(['skipped' => true, 'reason' => 'unknown_event'], 'Evento no reconocido.');
            }
        }

        $ticketId = $this->extractTicketId($payload);
        if ($ticketId <= 0) {
            return ServiceResult::fail('Payload sin id de ticket.');
        }

        return $this->auditTicket($ticketId, $event);
    }

    public function auditTicket(int $ticketId, string $event = 'update'): ServiceResult
    {
        $debounce = $this->settings->webhookDebounceSeconds();
        $since    = $this->state->secondsSinceProcessed($ticketId);
        if ($since !== null && $since < $debounce) {
            return ServiceResult::ok(
                ['skipped' => true, 'reason' => 'debounce', 'ticket_id' => $ticketId, 'seconds' => $since],
                'Omitido por debounce.',
            );
        }

        $base = $this->query->ticketById($ticketId);
        if ($base === null) {
            return ServiceResult::fail("Ticket #{$ticketId} no encontrado en GLPI (o eliminado).");
        }

        $agent = $this->resolveAgentForTicket($ticketId);
        $glpiId = (int) $agent['glpi_user_id'];
        $ctx    = $this->runner->buildContext();
        $ticket = $this->assembleTicket($base, $glpiId, $agent, $ctx);

        $findings = [];
        $now      = date('Y-m-d H:i:s');
        foreach ($this->rules->all() as $rule) {
            if (! in_array($rule->key(), self::LIVE_RULE_KEYS, true)) {
                continue;
            }
            foreach ($rule->evaluate($ticket, $ctx) as $dev) {
                $field = (string) ($dev['field_affected'] ?? '');
                $findings[] = [
                    'glpi_ticket_id'    => $ticketId,
                    'glpi_ticket_title' => mb_substr((string) $ticket['name'], 0, 255),
                    'glpi_user_id'      => $glpiId,
                    'nexus_user_id'     => $agent['nexus_user_id'],
                    'agent_name'        => mb_substr((string) $agent['name'], 0, 150),
                    'rule_key'          => $rule->key(),
                    'rule_name'         => $rule->name(),
                    'severity'          => $rule->severity(),
                    'field_key'         => mb_substr($field !== '' ? $field : '_', 0, 120),
                    'field_affected'    => $field !== '' ? $field : null,
                    'expected_value'    => $dev['expected_value'] ?? null,
                    'actual_value'      => $dev['actual_value'] ?? null,
                    'detail'            => (string) ($dev['detail'] ?? ''),
                    'manual_reference'  => $rule->manualReference(),
                    'kpi_mapping'       => $rule->kpiMapping(),
                    'event_source'      => $event,
                    'last_seen_at'      => $now,
                ];
            }
        }

        $openCount = $this->syncFindings($ticketId, $findings, $event, $now);
        $this->state->touch($ticketId, $event, $openCount);

        return ServiceResult::ok(
            [
                'ticket_id'  => $ticketId,
                'event'      => $event,
                'findings'   => count($findings),
                'open_count' => $openCount,
                'agent'      => $agent['name'] !== '' ? $agent['name'] : ('GLPI #' . $glpiId),
            ],
            "Ticket #{$ticketId}: " . count($findings) . " hallazgo(s), {$openCount} abiertos.",
        );
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     */
    private function syncFindings(int $ticketId, array $findings, string $event, string $now): int
    {
        $seenKeys = [];
        foreach ($findings as $f) {
            $key = $f['rule_key'] . "\0" . $f['field_key'];
            $seenKeys[$key] = true;

            $existing = $this->live
                ->where('glpi_ticket_id', $ticketId)
                ->where('rule_key', $f['rule_key'])
                ->where('field_key', $f['field_key'])
                ->first();

            if ($existing === null) {
                $this->live->insert(array_merge($f, [
                    'status'        => 'open',
                    'first_seen_at' => $now,
                    'resolved_at'   => null,
                ]));
                continue;
            }

            $this->live->update((int) $existing['id'], [
                'glpi_ticket_title' => $f['glpi_ticket_title'],
                'glpi_user_id'      => $f['glpi_user_id'],
                'nexus_user_id'     => $f['nexus_user_id'],
                'agent_name'        => $f['agent_name'],
                'rule_name'         => $f['rule_name'],
                'severity'          => $f['severity'],
                'field_affected'    => $f['field_affected'],
                'expected_value'    => $f['expected_value'],
                'actual_value'      => $f['actual_value'],
                'detail'            => $f['detail'],
                'manual_reference'  => $f['manual_reference'],
                'kpi_mapping'       => $f['kpi_mapping'],
                'status'            => 'open',
                'event_source'      => $event,
                'last_seen_at'      => $now,
                'resolved_at'       => null,
            ]);
        }

        foreach ($this->live->openForTicket($ticketId) as $row) {
            $key = $row['rule_key'] . "\0" . $row['field_key'];
            if (isset($seenKeys[$key])) {
                continue;
            }
            $this->live->update((int) $row['id'], [
                'status'      => 'resolved',
                'resolved_at' => $now,
                'last_seen_at' => $now,
            ]);
        }

        return $this->live->where('glpi_ticket_id', $ticketId)->where('status', 'open')->countAllResults();
    }

    /**
     * @param array<string,mixed> $base
     * @param array{glpi_user_id:int,nexus_user_id:?int,name:string} $agent
     * @return array<string,mixed>
     */
    private function assembleTicket(array $base, int $glpiId, array $agent, AuditContext $ctx): array
    {
        $ticketId = (int) $base['id'];
        $ids      = [$ticketId];
        $assignments = $this->query->assignmentsForTickets($ids);
        $logs        = $this->query->logsForTickets($ids);
        $activity    = $this->query->activityForTickets($ids, $glpiId > 0 ? $glpiId : 0);
        $pluginRows  = [];
        foreach (array_unique(array_values($ctx->tabContainers)) as $cid) {
            $container = $ctx->containers[$cid] ?? null;
            if ($container === null) {
                continue;
            }
            $pluginRows[$cid] = $this->query->pluginRowsForContainer($container, $ids);
        }

        $plugin = [];
        foreach ($pluginRows as $cid => $byTicket) {
            if (isset($byTicket[$ticketId])) {
                $plugin[$cid] = $byTicket[$ticketId];
            }
        }

        return $base + [
            'category_name'      => $ctx->categoryName((int) $base['itilcategories_id']),
            'agent_glpi_user_id' => $glpiId,
            'agent_user_name'    => $glpiId > 0 ? $this->query->agentUserName($glpiId) : '',
            'assigned_user_ids'  => $assignments[$ticketId] ?? [],
            'plugin'             => $plugin,
            'logs'               => $logs[$ticketId] ?? [],
            'activity'           => $activity[$ticketId] ?? [
                'followups' => 0, 'tasks' => 0, 'solutions' => 0,
                'agent_updates' => 0, 'last_agent_activity' => null,
            ],
        ];
    }

    /**
     * Prefer a Nexus-mapped assignee; else mapped requester; else first assignee.
     *
     * @return array{glpi_user_id:int,nexus_user_id:?int,name:string}
     */
    private function resolveAgentForTicket(int $ticketId): array
    {
        $links  = $this->query->ticketUserIds($ticketId);
        $mapped = $this->mappedAgentsByGlpi();

        foreach ($links['assignees'] as $uid) {
            if (isset($mapped[$uid])) {
                return $mapped[$uid];
            }
        }
        foreach ($links['requesters'] as $uid) {
            if (isset($mapped[$uid])) {
                return $mapped[$uid];
            }
        }
        $fallback = $links['assignees'][0] ?? $links['requesters'][0] ?? 0;
        if ($fallback > 0) {
            $name = $this->query->agentDisplayName($fallback);

            return ['glpi_user_id' => $fallback, 'nexus_user_id' => null, 'name' => $name];
        }

        return ['glpi_user_id' => 0, 'nexus_user_id' => null, 'name' => ''];
    }

    /**
     * @return array<int,array{glpi_user_id:int,nexus_user_id:int,name:string}>
     */
    private function mappedAgentsByGlpi(): array
    {
        $rows = \Config\Database::connect()->table('core_users')
            ->select('id, name, glpi_user_id')
            ->where('glpi_user_id IS NOT NULL')
            ->where('glpi_user_id >', 0)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $gid = (int) $r['glpi_user_id'];
            $out[$gid] = [
                'glpi_user_id'  => $gid,
                'nexus_user_id' => (int) $r['id'],
                'name'          => (string) $r['name'],
            ];
        }

        return $out;
    }

    /** @param array<string,mixed> $payload */
    private function extractTicketId(array $payload): int
    {
        $candidates = [
            $payload['item']['id'] ?? null,
            $payload['item']['ID'] ?? null,
            $payload['items_id'] ?? null,
            $payload['tickets_id'] ?? null,
            $payload['id'] ?? null,
            $payload['ticket_id'] ?? null,
        ];
        foreach ($candidates as $c) {
            if (is_numeric($c) && (int) $c > 0) {
                return (int) $c;
            }
        }

        return 0;
    }

    private function normalizeEvent(string $raw): string
    {
        $e = strtolower(trim($raw));
        if ($e === '') {
            return '';
        }
        if (str_contains($e, 'new') || str_contains($e, 'add') || $e === 'create') {
            return 'create';
        }
        if (str_contains($e, 'update') || str_contains($e, 'change')) {
            return 'update';
        }

        return $e;
    }
}
