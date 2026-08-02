<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class AgentRunStatsModel extends Model
{
    protected $table         = 'helpdesk_supervisor_agent_run_stats';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'audit_run_id', 'glpi_user_id', 'nexus_user_id', 'agent_name',
        'total_tickets', 'open_tickets', 'created_at',
    ];

    public function forRunAgent(int $auditRunId, int $glpiUserId): ?array
    {
        return $this->where('audit_run_id', $auditRunId)->where('glpi_user_id', $glpiUserId)->first();
    }
}
