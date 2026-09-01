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
        'is_confirmed', 'confirmed_at', 'confirmed_by_user_id',
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
            ->orderBy('rule_key', 'ASC')
            ->findAll();
    }

    public function countForAgent(int $auditRunId, int $glpiUserId): int
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->countAllResults();
    }

    /** @return array<int,array<string,mixed>> */
    public function forAgentPaginated(int $auditRunId, int $glpiUserId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->orderBy('glpi_ticket_id', 'ASC')
            ->orderBy('rule_key', 'ASC')
            ->findAll($perPage, $offset);
    }

    /** @return array<int,array<string,mixed>> */
    public function forAgentExport(int $auditRunId, int $glpiUserId, int $limit = 50000): array
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->orderBy('glpi_ticket_id', 'ASC')
            ->orderBy('rule_key', 'ASC')
            ->findAll($limit);
    }

    /**
     * Rule breakdown for one agent in a run.
     *
     * @return array<int,array<string,mixed>>
     */
    public function ruleSummaryForAgent(int $auditRunId, int $glpiUserId): array
    {
        return $this->select('rule_key, rule_name, severity')
            ->selectCount('id', 'count')
            ->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->groupBy('rule_key, rule_name, severity')
            ->orderBy('count', 'DESC')
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

    public function countForRule(int $auditRunId, string $ruleKey): int
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('rule_key', $ruleKey)
            ->countAllResults();
    }

    /** @return array<int,array<string,mixed>> */
    public function forRulePaginated(int $auditRunId, string $ruleKey, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->where('audit_run_id', $auditRunId)
            ->where('rule_key', $ruleKey)
            ->orderBy('agent_name', 'ASC')
            ->orderBy('glpi_ticket_id', 'ASC')
            ->findAll($perPage, $offset);
    }

    /** @return array<int,array<string,mixed>> */
    public function forRuleExport(int $auditRunId, string $ruleKey, int $limit = 50000): array
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('rule_key', $ruleKey)
            ->orderBy('agent_name', 'ASC')
            ->orderBy('glpi_ticket_id', 'ASC')
            ->findAll($limit);
    }

    /**
     * Updates is_confirmed only for deviation ids on the current page (safe with pagination).
     */
    public function setConfirmedForAgentRunPage(
        int $auditRunId,
        int $glpiUserId,
        array $pageIds,
        array $confirmedIds,
        ?int $byUserId,
    ): int {
        $now         = date('Y-m-d H:i:s');
        $pageIds     = array_values(array_unique(array_map('intval', $pageIds)));
        $confirmed   = array_flip(array_values(array_unique(array_map('intval', $confirmedIds))));

        foreach ($pageIds as $id) {
            if ($id <= 0) {
                continue;
            }
            $data = isset($confirmed[$id])
                ? ['is_confirmed' => 1, 'confirmed_at' => $now, 'confirmed_by_user_id' => $byUserId]
                : ['is_confirmed' => 0, 'confirmed_at' => null, 'confirmed_by_user_id' => null];

            $this->where('id', $id)
                ->where('audit_run_id', $auditRunId)
                ->where('glpi_user_id', $glpiUserId)
                ->set($data)
                ->update();
        }

        return $this->where('audit_run_id', $auditRunId)->where('glpi_user_id', $glpiUserId)
            ->where('is_confirmed', 1)->countAllResults();
    }

    /**
     * Sets is_confirmed for one agent's deviations in a run: the ids in
     * $confirmedIds become confirmed, the rest of that agent+run become
     * unconfirmed. Returns how many are now confirmed.
     */
    public function setConfirmedForAgentRun(int $auditRunId, int $glpiUserId, array $confirmedIds, ?int $byUserId): int
    {
        $now = date('Y-m-d H:i:s');
        $ids = array_values(array_unique(array_map('intval', $confirmedIds)));

        // Unconfirm everything for this agent+run first.
        $this->where('audit_run_id', $auditRunId)->where('glpi_user_id', $glpiUserId)
            ->set(['is_confirmed' => 0, 'confirmed_at' => null, 'confirmed_by_user_id' => null])
            ->update();

        if ($ids === []) {
            return 0;
        }

        // Confirm the selected ones (restricted to this agent+run for safety).
        $this->whereIn('id', $ids)
            ->where('audit_run_id', $auditRunId)->where('glpi_user_id', $glpiUserId)
            ->set(['is_confirmed' => 1, 'confirmed_at' => $now, 'confirmed_by_user_id' => $byUserId])
            ->update();

        return $this->where('audit_run_id', $auditRunId)->where('glpi_user_id', $glpiUserId)
            ->where('is_confirmed', 1)->countAllResults();
    }

    /**
     * Confirmed deviations for an agent across all runs, with the run period, for
     * the agent's own self-view. Most recent period first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function confirmedForAgentWithPeriod(int $glpiUserId): array
    {
        return $this->select('helpdesk_supervisor_deviations.*, r.period_start, r.period_end')
            ->join('helpdesk_supervisor_audit_runs r', 'r.id = helpdesk_supervisor_deviations.audit_run_id')
            ->where('helpdesk_supervisor_deviations.glpi_user_id', $glpiUserId)
            ->where('helpdesk_supervisor_deviations.is_confirmed', 1)
            ->orderBy('r.period_start', 'DESC')
            ->orderBy('helpdesk_supervisor_deviations.rule_key', 'ASC')
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
