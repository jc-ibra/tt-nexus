<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\HelpdeskSupervisor\Models\AgentRunStatsModel;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Models\EscalationModel;

/**
 * Read-only bridge over the HelpdeskSupervisor data, consumed cross-module by:
 *  - ServiceDesk's agent self-view ("Mi desempeño"): confirmed deviations +
 *    valid escalations for the logged-in agent.
 *  - AgentKpis (Fase 3): KPI denominators/numerators per agent and period.
 *
 * Other modules must go through this service, never the tables directly.
 */
class HelpdeskSupervisorBridge
{
    public function __construct(
        private DeviationModel $deviations,
        private EscalationModel $escalations,
        private AuditRunModel $runs,
        private AgentRunStatsModel $stats,
    ) {}

    // ------------------------------------------------------------------
    // Agent self-view (ServiceDesk)
    // ------------------------------------------------------------------

    /**
     * Confirmed deviations for an agent, grouped by audit period (newest first).
     *
     * @return array<int,array{period_start:string,period_end:string,deviations:array<int,array<string,mixed>>}>
     */
    public function confirmedDeviationsForAgent(int $glpiUserId): array
    {
        $rows = $this->deviations->confirmedForAgentWithPeriod($glpiUserId);

        $groups = [];
        foreach ($rows as $r) {
            $key = $r['period_start'] . '|' . $r['period_end'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'period_start' => (string) $r['period_start'],
                    'period_end'   => (string) $r['period_end'],
                    'deviations'   => [],
                ];
            }
            $groups[$key]['deviations'][] = $r;
        }
        return array_values($groups);
    }

    /** Valid escalations for an agent (newest first). @return array<int,array<string,mixed>> */
    public function validEscalationsForAgent(int $glpiUserId): array
    {
        return $this->escalations
            ->where('glpi_user_id', $glpiUserId)
            ->where('is_valid', 1)
            ->orderBy('escalation_date', 'DESC')
            ->findAll();
    }

    // ------------------------------------------------------------------
    // KPI feed (AgentKpis, Fase 3)
    // ------------------------------------------------------------------

    /** Latest completed run for a natural month (day 1 to last day). */
    public function getLatestRun(int $year, int $month): ?array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        return $this->runs->latestCompletedForPeriod($start, $end);
    }

    /**
     * Mapped agents (Nexus users with a glpi_user_id).
     *
     * @return array<int,array{glpi_user_id:int,nexus_user_id:int,name:string,email:string}>
     */
    public function getMappedAgents(): array
    {
        $rows = \Config\Database::connect()->table('core_users')
            ->select('id, name, email, glpi_user_id')
            ->where('glpi_user_id IS NOT NULL')
            ->where('glpi_user_id >', 0)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn($r) => [
            'glpi_user_id'  => (int) $r['glpi_user_id'],
            'nexus_user_id' => (int) $r['id'],
            'name'          => (string) $r['name'],
            'email'         => (string) $r['email'],
        ], $rows);
    }

    /** Per-agent ticket stats for a run: ['total_tickets','open_tickets']. */
    public function getAgentStats(int $auditRunId, int $glpiUserId): array
    {
        $row = $this->stats->forRunAgent($auditRunId, $glpiUserId);
        return [
            'total_tickets' => (int) ($row['total_tickets'] ?? 0),
            'open_tickets'  => (int) ($row['open_tickets'] ?? 0),
        ];
    }

    /** Distinct tickets that failed a KPI for an agent in a run. */
    public function getDeviationTicketCountByKpi(int $auditRunId, int $glpiUserId, string $kpiMapping): int
    {
        return count($this->deviations->failingTicketIds($auditRunId, $glpiUserId, $kpiMapping));
    }

    /** @return int[] ticket ids failing a KPI (for snapshots/drill-down). */
    public function getFailingTicketIds(int $auditRunId, int $glpiUserId, string $kpiMapping): array
    {
        return $this->deviations->failingTicketIds($auditRunId, $glpiUserId, $kpiMapping);
    }

    /** Valid escalations count for an agent in a month (KPI 5). */
    public function getEscalationCount(int $glpiUserId, int $year, int $month): int
    {
        return $this->escalations->validCountForMonth($glpiUserId, $year, $month);
    }
}
