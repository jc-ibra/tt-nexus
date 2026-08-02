<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table         = 'helpdesk_supervisor_notifications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'audit_run_id', 'glpi_user_id', 'nexus_user_id', 'agent_name', 'agent_email',
        'period_start', 'period_end', 'total_deviations', 'ai_draft_body', 'final_body',
        'excel_path', 'status', 'sent_at', 'sent_by_user_id', 'error_message',
        'ai_tokens_input', 'ai_tokens_output',
    ];

    /** Existing notification for an agent in a run, or null. */
    public function forAgentRun(int $auditRunId, int $glpiUserId): ?array
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Whether a notification was already SENT for this agent+run. */
    public function alreadySent(int $auditRunId, int $glpiUserId): bool
    {
        return $this->where('audit_run_id', $auditRunId)
            ->where('glpi_user_id', $glpiUserId)
            ->where('status', 'sent')
            ->countAllResults() > 0;
    }

    public function recent(int $limit = 100): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }
}
