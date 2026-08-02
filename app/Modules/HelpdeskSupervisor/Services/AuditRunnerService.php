<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\CoordinatorMapModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Rules\AuditContext;
use App\Modules\HelpdeskSupervisor\Rules\RuleRegistry;
use App\Modules\ServiceDesk\Services\GlpiSchemaIntrospector;

/**
 * Orchestrates an audit run: creates the run, resolves the mapped agents, pulls
 * each agent's tickets and related data from GLPI in bulk, evaluates every rule
 * per ticket, and persists the deviations. Runs are immutable history — a
 * re-audit of the same period creates a fresh run.
 */
class AuditRunnerService
{
    /**
     * Logical tab keys mapped to plugin containers via settings. ID Externo is
     * NOT here: it is the native glpi_tickets.externalid field, read directly by
     * ExternalIdRule (no container mapping needed).
     */
    public const TAB_KEYS = [
        'clientes_externos',
        'areas_internas',
        'control_activos',
        'control_envios',
        'ids',
    ];

    public function __construct(
        private HelpdeskSupervisorSettings $settings,
        private GlpiAuditQueryService $query,
        private GlpiSchemaIntrospector $introspector,
        private RuleRegistry $rules,
        private CoordinatorMapModel $coordinatorMap,
        private AuditRunModel $runModel,
        private DeviationModel $deviationModel,
    ) {}

    /**
     * Runs an audit for a period. When $agentGlpiUserId is null, every mapped
     * agent is audited; otherwise only that one.
     */
    public function run(string $periodStart, string $periodEnd, ?int $agentGlpiUserId, ?int $runByUserId): ServiceResult
    {
        if (! $this->query->isConfigured()) {
            return ServiceResult::fail('La conexión a GLPI no está configurada.');
        }

        $now = date('Y-m-d H:i:s');
        $runId = (int) $this->runModel->insert([
            'period_start'       => $periodStart,
            'period_end'         => $periodEnd,
            'agent_glpi_user_id' => $agentGlpiUserId,
            'status'             => 'running',
            'run_by_user_id'     => $runByUserId,
            'started_at'         => $now,
            'created_at'         => $now,
        ], true);

        try {
            $ctx    = $this->buildContext();
            $agents = $this->resolveAgents($agentGlpiUserId);

            $totalTickets = 0;
            $totalDeviations = 0;
            $agentsAudited = 0;
            $deviationRows = [];

            foreach ($agents as $agent) {
                $agentsAudited++;
                $glpiId = (int) $agent['glpi_user_id'];

                $tickets = $this->query->ticketsForPeriod($glpiId, $periodStart, $periodEnd);
                if ($tickets === []) {
                    continue;
                }
                $ticketIds = array_keys($tickets);
                $totalTickets += count($ticketIds);

                $assignments = $this->query->assignmentsForTickets($ticketIds);
                $logs        = $this->query->logsForTickets($ticketIds);
                $activity    = $this->query->activityForTickets($ticketIds, $glpiId);
                $pluginRows  = $this->pluginRows($ctx, $ticketIds);

                $agentUserName = $this->query->agentUserName($glpiId);
                $agentName     = $agent['name'] !== '' ? $agent['name'] : $this->query->agentDisplayName($glpiId);

                foreach ($tickets as $ticketId => $base) {
                    $ticket = $base + [
                        'category_name'      => $ctx->categoryName((int) $base['itilcategories_id']),
                        'agent_glpi_user_id' => $glpiId,
                        'agent_user_name'    => $agentUserName,
                        'assigned_user_ids'  => $assignments[$ticketId] ?? [],
                        'plugin'             => $this->pluginForTicket($pluginRows, (int) $ticketId),
                        'logs'               => $logs[$ticketId] ?? [],
                        'activity'           => $activity[$ticketId] ?? [
                            'followups' => 0, 'tasks' => 0, 'solutions' => 0,
                            'agent_updates' => 0, 'last_agent_activity' => null,
                        ],
                    ];

                    foreach ($this->rules->all() as $rule) {
                        foreach ($rule->evaluate($ticket, $ctx) as $dev) {
                            $deviationRows[] = [
                                'audit_run_id'     => $runId,
                                'glpi_ticket_id'   => (int) $ticketId,
                                'glpi_ticket_title' => mb_substr((string) $ticket['name'], 0, 255),
                                'glpi_user_id'     => $glpiId,
                                'nexus_user_id'    => $agent['nexus_user_id'],
                                'agent_name'       => mb_substr($agentName, 0, 150),
                                'rule_key'         => $rule->key(),
                                'rule_name'        => $rule->name(),
                                'severity'         => $rule->severity(),
                                'field_affected'   => $dev['field_affected'] ?? null,
                                'expected_value'   => $dev['expected_value'] ?? null,
                                'actual_value'     => $dev['actual_value'] ?? null,
                                'detail'           => (string) ($dev['detail'] ?? ''),
                                'manual_reference' => $rule->manualReference(),
                                'kpi_mapping'      => $rule->kpiMapping(),
                                'created_at'       => $now,
                            ];
                            $totalDeviations++;
                        }
                    }
                }
            }

            if ($deviationRows !== []) {
                // Insert in chunks to stay well under packet limits on large runs.
                foreach (array_chunk($deviationRows, 500) as $chunk) {
                    $this->deviationModel->insertBatch($chunk);
                }
            }

            $this->runModel->update($runId, [
                'total_tickets_audited'  => $totalTickets,
                'total_deviations_found' => $totalDeviations,
                'total_agents_audited'   => $agentsAudited,
                'status'                 => 'completed',
                'completed_at'           => date('Y-m-d H:i:s'),
            ]);

            return ServiceResult::ok(
                ['run_id' => $runId, 'tickets' => $totalTickets, 'deviations' => $totalDeviations, 'agents' => $agentsAudited],
                "Auditoría completada: {$totalTickets} tickets, {$totalDeviations} desviaciones, {$agentsAudited} agentes.",
            );
        } catch (\Throwable $e) {
            log_message('error', '[HelpdeskSupervisor] audit run failed: ' . $e->getMessage());
            $this->runModel->update($runId, [
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at'  => date('Y-m-d H:i:s'),
            ]);
            return ServiceResult::fail('La auditoría falló: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Context assembly
    // ------------------------------------------------------------------

    private function buildContext(): AuditContext
    {
        $containers = $this->introspector->containers();

        // Resolve tab -> container id from settings (unset = 0 = rule inactive).
        $all = $this->settings->all();
        $tabContainers = [];
        foreach (self::TAB_KEYS as $tab) {
            $cid = (int) ($all['tab_container_' . $tab] ?? 0);
            if ($cid > 0) {
                $tabContainers[$tab] = $cid;
            }
        }

        return new AuditContext(
            categories: $this->query->categoriesIndex(),
            coordinatorByState: $this->coordinatorsWithIds(),
            containers: $containers,
            tabContainers: $tabContainers,
            openingDateToleranceSeconds: $this->settings->openingDateToleranceSeconds(),
            businessDaysAbandonment: $this->settings->businessDaysAbandonment(),
            dropdownValues: $this->dropdownValues($containers, $tabContainers),
        );
    }

    /**
     * Coordinator map keyed by normalized state, with each coordinator's GLPI
     * user id resolved from its name when not already stored.
     *
     * @return array<string,array<string,mixed>>
     */
    private function coordinatorsWithIds(): array
    {
        $map      = $this->coordinatorMap->byStateName();
        $resolved = [];
        foreach ($map as $state => $row) {
            if (empty($row['coordinator_glpi_user_id'])) {
                $name = (string) ($row['coordinator_name'] ?? '');
                if ($name !== '') {
                    $id = $resolved[$name] ?? ($resolved[$name] = $this->query->resolveUserIdByName($name));
                    $row['coordinator_glpi_user_id'] = $id;
                }
            }
            $map[$state] = $row;
        }
        return $map;
    }

    /**
     * Builds id => name maps for every dropdown field of the configured tab
     * containers, so rules can resolve FK values to labels. Each dropdown table
     * is queried once.
     *
     * @param array<int,array<string,mixed>> $containers
     * @param array<string,int>              $tabContainers
     * @return array<int,array<string,array<int,string>>> containerId => labelLower => (id => name)
     */
    private function dropdownValues(array $containers, array $tabContainers): array
    {
        $out    = [];
        $tables = []; // dropdownTable => resolved values (cache across containers)
        foreach (array_unique(array_values($tabContainers)) as $cid) {
            $fields = $containers[$cid]['fields'] ?? [];
            foreach ($fields as $f) {
                if (($f['type'] ?? '') !== 'dropdown' || empty($f['dropdownTable'])) {
                    continue;
                }
                $table = (string) $f['dropdownTable'];
                $tables[$table] ??= $this->query->dropdownValues($table);
                $out[$cid][mb_strtolower(trim((string) $f['label']))] = $tables[$table];
            }
        }
        return $out;
    }

    /**
     * Fetches plugin data rows for every configured tab container, for the given
     * tickets. Keyed by container id, then ticket id.
     *
     * @param int[] $ticketIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function pluginRows(AuditContext $ctx, array $ticketIds): array
    {
        $out = [];
        foreach (array_unique(array_values($ctx->tabContainers)) as $cid) {
            $container = $ctx->containers[$cid] ?? null;
            if ($container === null) {
                continue;
            }
            $out[$cid] = $this->query->pluginRowsForContainer($container, $ticketIds);
        }
        return $out;
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $pluginRows
     * @return array<int,array<string,mixed>> containerId => raw row for this ticket
     */
    private function pluginForTicket(array $pluginRows, int $ticketId): array
    {
        $out = [];
        foreach ($pluginRows as $cid => $byTicket) {
            if (isset($byTicket[$ticketId])) {
                $out[$cid] = $byTicket[$ticketId];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Agents
    // ------------------------------------------------------------------

    /**
     * Mapped agents to audit. Each: ['glpi_user_id','nexus_user_id','name'].
     * When $only is set, restricts to that GLPI id (mapped or not).
     *
     * @return array<int,array{glpi_user_id:int,nexus_user_id:?int,name:string}>
     */
    private function resolveAgents(?int $only): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('core_users')
            ->select('id, name, glpi_user_id')
            ->where('glpi_user_id IS NOT NULL')
            ->where('glpi_user_id >', 0)
            ->get()->getResultArray();

        $byGlpi = [];
        foreach ($rows as $r) {
            $byGlpi[(int) $r['glpi_user_id']] = [
                'glpi_user_id' => (int) $r['glpi_user_id'],
                'nexus_user_id' => (int) $r['id'],
                'name'         => (string) $r['name'],
            ];
        }

        if ($only !== null && $only > 0) {
            return [$byGlpi[$only] ?? ['glpi_user_id' => $only, 'nexus_user_id' => null, 'name' => '']];
        }

        return array_values($byGlpi);
    }
}
