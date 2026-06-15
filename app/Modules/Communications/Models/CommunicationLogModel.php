<?php

namespace App\Modules\Communications\Models;

use CodeIgniter\Model;

class CommunicationLogModel extends Model
{
    protected $table      = 'comms_communication_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'communication_id', 'recipient_id', 'email', 'name',
        'status', 'error_message', 'sent_at', 'tracking_token', 'opened_at',
    ];

    protected $useTimestamps = true;

    public function getForCommunication(int $communicationId, int $perPage = 50): array
    {
        return $this->where('communication_id', $communicationId)
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage);
    }

    public function getStats(int $communicationId): array
    {
        $result = $this->db->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'sent') AS sent,
                SUM(status = 'failed') AS failed,
                SUM(status = 'queued') AS queued,
                SUM(status = 'bounced') AS bounced,
                SUM(opened_at IS NOT NULL) AS opened
            FROM communication_logs
            WHERE communication_id = ?
        ", [$communicationId])->getRowArray();

        return $result ?? ['total' => 0, 'sent' => 0, 'failed' => 0, 'queued' => 0, 'bounced' => 0, 'opened' => 0];
    }

    public function bulkInsertForCommunication(int $communicationId, array $recipients, bool $withTracking = false): int
    {
        if (empty($recipients)) {
            return 0;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($recipients as $r) {
            $row = [
                'communication_id' => $communicationId,
                'recipient_id'     => $r['id'] ?? null,
                'email'            => $r['email'],
                'name'             => $r['name'],
                'status'           => 'queued',
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            if ($withTracking) {
                $row['tracking_token'] = bin2hex(random_bytes(32));
            }

            $rows[] = $row;
        }

        $this->db->table($this->table)->insertBatch($rows);

        return count($rows);
    }

    public function findByToken(string $token): ?array
    {
        return $this->where('tracking_token', $token)->first();
    }

    public function markOpened(int $logId): void
    {
        $this->update($logId, ['opened_at' => date('Y-m-d H:i:s')]);
    }
}
