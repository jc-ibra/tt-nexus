<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Model;

/**
 * Audit log of backlog report sends (servicedesk_backlog_runs). Each attempt —
 * scheduled, manual or test — records what went out and whether it failed.
 */
class ServiceDeskBacklogRunModel extends Model
{
    protected $table         = 'servicedesk_backlog_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'report_date', 'trigger', 'status', 'total_open',
        'recipients', 'error', 'triggered_by', 'created_at',
    ];

    /**
     * Records one send attempt and returns the new row id.
     */
    public function record(array $data): int
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->insert($data);
        return (int) $this->getInsertID();
    }

    /**
     * Most recent runs, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 10): array
    {
        return $this->orderBy('id', 'DESC')->findAll(max(1, $limit));
    }

    /**
     * Whether a successful scheduled/manual report already went out for a date.
     */
    public function sentOn(string $date): bool
    {
        return $this->where('report_date', $date)
            ->where('status', 'ok')
            ->whereIn('trigger', ['scheduled', 'manual'])
            ->countAllResults() > 0;
    }
}
