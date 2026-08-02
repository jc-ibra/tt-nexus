<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\AgentKpis\Models\KpiSnapshotModel;
use App\Modules\AgentKpis\Models\MonthlyEvaluationModel;

/**
 * Computes the quantitative side of the monthly evaluation from HelpdeskSupervisor
 * data (via the shared bridge). KPI thresholds and the level table follow the
 * evaluation system (Fase 3 spec §5).
 *
 * Note: KPIs count every audited deviation with the matching kpi_mapping (per the
 * Fase 3 spec). The supervisor's "procede" confirmation only governs what the
 * agent sees in Service Desk, not the KPI denominators.
 */
class KpiCalculationService
{
    public function __construct(
        private MonthlyEvaluationModel $evaluations,
        private KpiSnapshotModel $snapshots,
    ) {}

    /**
     * Generates (or recalculates) the quantitative evaluations for every mapped
     * agent for a month. Requires a completed HelpdeskSupervisor run for it.
     */
    public function generateForMonth(int $year, int $month, ?int $byUserId): ServiceResult
    {
        $bridge = service('helpdeskBridge');
        $run    = $bridge->getLatestRun($year, $month);
        if ($run === null) {
            return ServiceResult::fail('No hay una auditoría completada para ese mes en Supervisor de Mesa. Córrela primero.');
        }
        $runId = (int) $run['id'];

        $count = 0;
        foreach ($bridge->getMappedAgents() as $agent) {
            $glpiId = (int) $agent['glpi_user_id'];
            $stats  = $bridge->getAgentStats($runId, $glpiId);
            $total  = (int) $stats['total_tickets'];
            if ($total === 0) {
                continue; // no tickets audited for this agent -> nothing to evaluate
            }
            $open = (int) $stats['open_tickets'];

            // KPI 1-3: (total - tickets_with_deviation) / total.
            $dev1 = $bridge->getDeviationTicketCountByKpi($runId, $glpiId, 'KPI-1');
            $dev2 = $bridge->getDeviationTicketCountByKpi($runId, $glpiId, 'KPI-2');
            $dev3 = $bridge->getDeviationTicketCountByKpi($runId, $glpiId, 'KPI-3');
            $dev4 = $bridge->getDeviationTicketCountByKpi($runId, $glpiId, 'KPI-4');

            $k1 = $this->positivePercent($total, $dev1);
            $k2 = $this->positivePercent($total, $dev2);
            $k3 = $this->positivePercent($total, $dev3);
            $k4 = $open > 0 ? round($dev4 / $open * 100, 2) : 0.0; // lower is better

            $s1 = $this->status($k1, 90, 75);
            $s2 = $this->status($k2, 92, 80);
            $s3 = $this->status($k3, 95, 85);
            $s4 = $this->statusInverse($k4, 5, 10);

            $esc = $bridge->getEscalationCount($glpiId, $year, $month);
            $s5  = $esc === 0 ? 'cumple' : ($esc <= 2 ? 'parcial' : 'no_cumple');

            $met     = (int) ($s1 === 'cumple') + (int) ($s2 === 'cumple') + (int) ($s3 === 'cumple') + (int) ($s4 === 'cumple') + (int) ($s5 === 'cumple');
            $level   = $this->level($met);
            $quant   = round($level * 0.80, 2);
            $blocked = $esc >= 3;

            $data = [
                'nexus_user_id'          => $agent['nexus_user_id'],
                'glpi_user_id'           => $glpiId,
                'agent_name'             => $agent['name'],
                'period_year'            => $year,
                'period_month'           => $month,
                'audit_run_id'           => $runId,
                'total_tickets'          => $total,
                'kpi1_value'             => $k1, 'kpi1_status' => $s1,
                'kpi2_value'             => $k2, 'kpi2_status' => $s2,
                'kpi3_value'             => $k3, 'kpi3_status' => $s3,
                'kpi4_value'             => $k4, 'kpi4_status' => $s4,
                'kpi5_escalations_count' => $esc, 'kpi5_status' => $s5,
                'kpis_met_count'         => $met,
                'quantitative_level'     => $level,
                'quantitative_score'     => $quant,
                'is_blocked'             => $blocked ? 1 : 0,
            ];

            $existing = $this->evaluations->forAgentMonth((int) $agent['nexus_user_id'], $year, $month);
            if ($existing) {
                // Preserve any qualitative work already captured; recompute final.
                $data['final_status'] = $blocked ? 'blocked'
                    : ($existing['qualitative_score'] !== null ? 'evaluated' : ($existing['final_status'] === 'draft' ? 'pending_qualitative' : $existing['final_status']));
                $data['final_score']  = $blocked ? null
                    : ($existing['qualitative_score'] !== null ? round($quant + (float) $existing['qualitative_score'], 2) : null);
                $this->evaluations->update((int) $existing['id'], $data);
                $evalId = (int) $existing['id'];
            } else {
                $data['final_status'] = $blocked ? 'blocked' : 'pending_qualitative';
                $data['final_score']  = null;
                $evalId = (int) $this->evaluations->insert($data, true);
            }

            $this->snapshots->replaceForEvaluation($evalId, [
                $this->snap($evalId, 1, $total, $total - $dev1, $k1, $s1),
                $this->snap($evalId, 2, $total, $total - $dev2, $k2, $s2),
                $this->snap($evalId, 3, $total, $total - $dev3, $k3, $s3),
                $this->snap($evalId, 4, $open, $dev4, $k4, $s4),
                $this->snap($evalId, 5, $esc, $esc, (float) $esc, $s5),
            ]);

            $count++;
        }

        return ServiceResult::ok(['evaluations' => $count], "Se generaron/actualizaron {$count} evaluación(es).");
    }

    private function positivePercent(int $total, int $ticketsWithDeviation): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        return round(max(0, $total - $ticketsWithDeviation) / $total * 100, 2);
    }

    /** Higher-is-better threshold. */
    private function status(float $value, float $cumple, float $parcial): string
    {
        if ($value >= $cumple) {
            return 'cumple';
        }
        return $value >= $parcial ? 'parcial' : 'no_cumple';
    }

    /** Lower-is-better threshold (KPI 4). */
    private function statusInverse(float $value, float $cumpleMax, float $parcialMax): string
    {
        if ($value <= $cumpleMax) {
            return 'cumple';
        }
        return $value <= $parcialMax ? 'parcial' : 'no_cumple';
    }

    private function level(int $met): float
    {
        return match (true) {
            $met >= 5 => 100.0,
            $met === 4 => 75.0,
            $met === 3 => 50.0,
            default   => 0.0,
        };
    }

    private function snap(int $evalId, int $kpi, int $denom, int $numer, float $value, string $status): array
    {
        return [
            'evaluation_id'           => $evalId,
            'kpi_number'              => $kpi,
            'total_tickets_evaluated' => $denom,
            'tickets_meeting_criteria' => $numer,
            'calculated_value'        => $value,
            'threshold_met'           => $status,
            'created_at'              => date('Y-m-d H:i:s'),
        ];
    }
}
