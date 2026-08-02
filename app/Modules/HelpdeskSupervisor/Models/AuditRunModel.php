<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class AuditRunModel extends Model
{
    protected $table         = 'helpdesk_supervisor_audit_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'period_start', 'period_end', 'agent_glpi_user_id',
        'total_tickets_audited', 'total_deviations_found', 'total_agents_audited',
        'status', 'error_message', 'run_by_user_id',
        'started_at', 'completed_at', 'created_at',
    ];

    /** Latest completed run whose period matches the given dates, or null. */
    public function latestCompletedForPeriod(string $periodStart, string $periodEnd): ?array
    {
        return $this->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->where('status', 'completed')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Most recent runs for the history screen. */
    public function recent(int $limit = 50): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }
}
