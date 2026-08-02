<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class DeviationModel extends Model
{
    protected $table         = 'helpdesk_supervisor_deviations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'audit_run_id', 'glpi_ticket_id', 'glpi_ticket_title', 'glpi_user_id',
        'nexus_user_id', 'agent_name', 'rule_key', 'rule_name', 'severity',
        'field_affected', 'expected_value', 'actual_value', 'detail',
        'manual_reference', 'kpi_mapping', 'created_at',
    ];

    /**
     * Per-agent aggregation for a run: tickets audited, deviations, criticals,
     * warnings. Ordered by deviation count desc (worst offenders first).
     *
     * @return array<int,array<string,mixed>>
     */
    public function agentSummary(int $auditRunId): array
    {
        return $this->select('glpi_user_id, nexus_user_id, agent_name')
            ->selectCount('id', 'deviations')
            ->select("COUNT(DISTINCT glpi_ticket_id) AS tickets_with_deviations", false)
            ->select("SUM(severity = 'critical') AS criticals", false)
            ->select("SUM(severity = 'warning') AS warnings", false)
            ->select("SUM(severity = 'info') AS infos", false)
            ->where('audit_run_id', $auditRunId)
            ->groupBy('glpi_user_id, nexus_user_id, agent_name')
            ->orderBy('deviations', 'DESC')
            ->findAll();
    }

    /**
     * Rule breakdown for a run: how many deviations per rule.
     *
     * @return array<int,array<string,mixed>>
     */
    public function ruleSummary(int $auditRunId): array
    {
        return $this->select('rule_key, rule_name, severity')
            ->selectCount('id', 'count')
            ->where('audit_run_id', $auditRunId)
            ->groupBy('rule_key, rule_name, severity')
            ->orderBy('count', 'DESC')
            ->findAll();
    }

    /** All deviations of one agent in a run. */
    public function forAgent(int $auditRunId, int $glpiUserId): array
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->orderBy('glpi_ticket_id', 'ASC')
            ->findAll();
    }

    /** All deviations of one rule in a run, grouped visually by agent. */
    public function forRule(int $auditRunId, string $ruleKey): array
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('rule_key', $ruleKey)
            ->orderBy('agent_name', 'ASC')
            ->orderBy('glpi_ticket_id', 'ASC')
            ->findAll();
    }

    /** Distinct tickets that failed a given KPI for an agent (for KPI drill-down). */
    public function failingTicketIds(int $auditRunId, int $glpiUserId, string $kpiMapping): array
    {
        $rows = $this->distinct()
            ->select('glpi_ticket_id')
            ->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->where('kpi_mapping', $kpiMapping)
            ->findAll();
        return array_map(static fn($r) => (int) $r['glpi_ticket_id'], $rows);
    }
}
