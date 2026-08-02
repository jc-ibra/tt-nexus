<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class EscalationModel extends Model
{
    protected $table         = 'helpdesk_supervisor_escalations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'glpi_ticket_id', 'glpi_user_id', 'nexus_user_id', 'agent_name',
        'escalation_date', 'reason', 'reported_by', 'validated_by_user_id',
        'period_year', 'period_month', 'is_valid',
    ];

    protected $validationRules = [
        'glpi_ticket_id'  => 'required|is_natural_no_zero',
        'glpi_user_id'    => 'required|is_natural_no_zero',
        'escalation_date' => 'required|valid_date[Y-m-d]',
        'reason'          => 'required|min_length[3]',
        'period_year'     => 'required|is_natural_no_zero',
        'period_month'    => 'required|greater_than[0]|less_than[13]',
    ];

    /** Count of valid escalations for an agent in a measurement month (KPI 5). */
    public function validCountForMonth(int $glpiUserId, int $year, int $month): int
    {
        return $this->where('glpi_user_id', $glpiUserId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('is_valid', 1)
            ->countAllResults();
    }

    /** Escalations of one agent in a month (for the agent detail screen). */
    public function forAgentMonth(int $glpiUserId, int $year, int $month): array
    {
        return $this->where('glpi_user_id', $glpiUserId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('escalation_date', 'DESC')
            ->findAll();
    }
}
