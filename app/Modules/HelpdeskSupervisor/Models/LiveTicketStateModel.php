<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class LiveTicketStateModel extends Model
{
    protected $table         = 'helpdesk_supervisor_live_ticket_state';
    protected $primaryKey    = 'glpi_ticket_id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'glpi_ticket_id', 'last_event', 'last_processed_at', 'last_open_count', 'updated_at',
    ];

    public function touch(int $ticketId, string $event, int $openCount): void
    {
        $now = date('Y-m-d H:i:s');
        $row = $this->find($ticketId);
        $data = [
            'glpi_ticket_id'    => $ticketId,
            'last_event'        => $event,
            'last_processed_at' => $now,
            'last_open_count'   => $openCount,
            'updated_at'        => $now,
        ];
        if ($row === null) {
            $this->insert($data);
        } else {
            $this->update($ticketId, $data);
        }
    }

    public function secondsSinceProcessed(int $ticketId): ?int
    {
        $row = $this->find($ticketId);
        if ($row === null || empty($row['last_processed_at'])) {
            return null;
        }
        $ts = strtotime((string) $row['last_processed_at']);
        if ($ts === false) {
            return null;
        }

        return max(0, time() - $ts);
    }
}
