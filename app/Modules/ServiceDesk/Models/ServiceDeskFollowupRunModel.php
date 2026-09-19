<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Audit trail of auto-seguimiento attempts (servicedesk_followup_runs). Backs
 * both the admin's recent-runs list and the per-ticket cooldown check the
 * worker relies on before sending another follow-up.
 */
class ServiceDeskFollowupRunModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function record(array $data): void
    {
        $this->db->table('servicedesk_followup_runs')->insert([
            'ticket_id'        => (int) ($data['ticket_id'] ?? 0),
            'glpi_category_id' => isset($data['glpi_category_id']) ? (int) $data['glpi_category_id'] : null,
            'status'           => (string) ($data['status'] ?? 'sent'),
            'assignee_email'   => $data['assignee_email'] ?? null,
            'requester_email'  => $data['requester_email'] ?? null,
            'glpi_followup_id' => isset($data['glpi_followup_id']) ? (int) $data['glpi_followup_id'] : null,
            'conversation_id'  => isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            'trigger'          => (string) ($data['trigger'] ?? 'scheduled'),
            'error'            => $data['error'] ?? null,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Timestamp of the last SUCCESSFUL follow-up sent for a ticket, or null when
     * none has gone out yet. Drives the per-ticket cooldown.
     */
    public function lastSentAt(int $ticketId): ?string
    {
        $row = $this->db->table('servicedesk_followup_runs')
            ->select('created_at')
            ->where('ticket_id', $ticketId)
            ->where('status', 'sent')
            ->orderBy('created_at', 'DESC')
            ->get(1)->getRow();

        return $row ? (string) $row->created_at : null;
    }

    /** Most recent runs, newest first, for the admin's history table. */
    public function recent(int $limit = 50): array
    {
        return $this->db->table('servicedesk_followup_runs')
            ->orderBy('created_at', 'DESC')
            ->get($limit)->getResultArray();
    }
}
