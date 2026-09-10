<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class LiveDeviationModel extends Model
{
    protected $table         = 'helpdesk_supervisor_live_deviations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'glpi_ticket_id', 'glpi_ticket_title', 'glpi_user_id', 'nexus_user_id', 'agent_name',
        'rule_key', 'rule_name', 'severity', 'field_key', 'field_affected',
        'expected_value', 'actual_value', 'detail', 'manual_reference', 'kpi_mapping',
        'status', 'event_source', 'first_seen_at', 'last_seen_at', 'resolved_at',
    ];

    /**
     * Open live deviations, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function openList(int $limit = 200, int $offset = 0, ?string $ruleKey = null): array
    {
        $q = $this->where('status', 'open')->orderBy('last_seen_at', 'DESC')->orderBy('id', 'DESC');
        if ($ruleKey !== null && $ruleKey !== '') {
            $q->where('rule_key', $ruleKey);
        }

        return $q->findAll($limit, $offset);
    }

    public function countOpen(?string $ruleKey = null): int
    {
        $q = $this->where('status', 'open');
        if ($ruleKey !== null && $ruleKey !== '') {
            $q->where('rule_key', $ruleKey);
        }

        return $q->countAllResults();
    }

    /**
     * Rule breakdown for open live deviations.
     *
     * @return array<int,array<string,mixed>>
     */
    public function openRuleSummary(): array
    {
        return $this->select('rule_key, rule_name, severity')
            ->selectCount('id', 'count')
            ->where('status', 'open')
            ->groupBy('rule_key, rule_name, severity')
            ->orderBy('count', 'DESC')
            ->findAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function openForTicket(int $glpiTicketId): array
    {
        return $this->where('glpi_ticket_id', $glpiTicketId)
            ->where('status', 'open')
            ->findAll();
    }
}
