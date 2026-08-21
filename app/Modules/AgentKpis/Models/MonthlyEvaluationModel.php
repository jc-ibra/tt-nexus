<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Models;

use CodeIgniter\Model;

class MonthlyEvaluationModel extends Model
{
    protected $table         = 'agent_kpis_monthly_evaluations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nexus_user_id', 'glpi_user_id', 'agent_name', 'period_year', 'period_month',
        'audit_run_id', 'total_tickets',
        'kpi1_value', 'kpi1_status', 'kpi2_value', 'kpi2_status', 'kpi3_value', 'kpi3_status',
        'kpi4_value', 'kpi4_status', 'kpi5_escalations_count', 'kpi5_status',
        'kpis_met_count', 'quantitative_level', 'quantitative_score',
        'qualitative_score_raw', 'qualitative_score', 'is_blocked', 'final_score', 'final_status',
        'evaluated_by_user_id', 'evaluated_at', 'agent_comments', 'supervisor_notes',
    ];

    /** All evaluations for a month, worst final score first (nulls/blocked last-ish). */
    public function forMonth(int $year, int $month): array
    {
        return $this->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('is_blocked', 'DESC')
            ->orderBy('final_score', 'ASC')
            ->orderBy('agent_name', 'ASC')
            ->findAll();
    }

    public function forAgentMonth(int $nexusUserId, int $year, int $month): ?array
    {
        return $this->where('nexus_user_id', $nexusUserId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
    }

    /**
     * Evaluations of one agent that the supervisor already closed, newest first.
     * Feeds the agent self-view in Service Desk: drafts and half-computed months
     * are deliberately left out.
     *
     * @param string[] $statuses allowed final_status values
     */
    public function publishedForAgent(int $nexusUserId, array $statuses): array
    {
        return $this->where('nexus_user_id', $nexusUserId)
            ->whereIn('final_status', $statuses)
            ->orderBy('period_year', 'DESC')
            ->orderBy('period_month', 'DESC')
            ->findAll();
    }

    /** One evaluation, but only if it belongs to the given agent (ownership check). */
    public function findForAgent(int $id, int $nexusUserId, array $statuses): ?array
    {
        return $this->where('id', $id)
            ->where('nexus_user_id', $nexusUserId)
            ->whereIn('final_status', $statuses)
            ->first();
    }

    /** Month-by-month history for one agent (oldest first for trend charts). */
    public function historyForAgent(int $nexusUserId): array
    {
        return $this->where('nexus_user_id', $nexusUserId)
            ->orderBy('period_year', 'ASC')
            ->orderBy('period_month', 'ASC')
            ->findAll();
    }
}
