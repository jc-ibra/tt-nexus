<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Models;

use CodeIgniter\Model;

/**
 * Append-only audit trail of technician actions. Never store secrets in payload.
 */
class ActivityLogModel extends Model
{
    protected $table         = 'techbot_activity_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'telegram_chat_id',
        'employee_id',
        'glpi_ticket_id',
        'action',
        'template_key',
        'glpi_followup_id',
        'glpi_status_before',
        'glpi_status_after',
        'payload',
        'ai_used',
        'ai_tokens_used',
        'result',
        'error_message',
        'created_at',
    ];

    /**
     * Records one action. `payload` is JSON-encoded here so callers pass arrays.
     */
    public function record(array $data): int
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
        }
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['result']     = ($data['result'] ?? 'success') === 'error' ? 'error' : 'success';

        return (int) $this->insert($data, true);
    }

    /**
     * Recent activity enriched with the employee name, with optional filters.
     *
     * @param array{employee_id?:int,glpi_ticket_id?:int,action?:string,result?:string,from?:string,to?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function recentWithEmployee(array $filters = [], int $limit = 100): array
    {
        $b = $this->select('techbot_activity_log.*, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = techbot_activity_log.employee_id', 'left');

        if (! empty($filters['employee_id'])) {
            $b->where('techbot_activity_log.employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['glpi_ticket_id'])) {
            $b->where('techbot_activity_log.glpi_ticket_id', (int) $filters['glpi_ticket_id']);
        }
        if (! empty($filters['action'])) {
            $b->where('techbot_activity_log.action', (string) $filters['action']);
        }
        if (! empty($filters['result'])) {
            $b->where('techbot_activity_log.result', (string) $filters['result']);
        }
        if (! empty($filters['from'])) {
            $b->where('techbot_activity_log.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $b->where('techbot_activity_log.created_at <=', $filters['to'] . ' 23:59:59');
        }

        return $b->orderBy('techbot_activity_log.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function findWithEmployee(int $id): ?array
    {
        return $this->select('techbot_activity_log.*, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = techbot_activity_log.employee_id', 'left')
            ->where('techbot_activity_log.id', $id)
            ->first();
    }

    /** Count of actions since a datetime (for dashboard KPIs). */
    public function countSince(string $since): int
    {
        return $this->where('created_at >=', $since)->countAllResults();
    }

    public function countErrorsSince(string $since): int
    {
        return $this->where('created_at >=', $since)->where('result', 'error')->countAllResults();
    }

    /** Most recent errors for the dashboard. */
    public function recentErrors(int $limit = 5): array
    {
        return $this->recentWithEmployee(['result' => 'error'], $limit);
    }

    /**
     * Latest activity timestamp per employee_id (for the "last access" column).
     *
     * @return array<int,string> employee_id => datetime
     */
    public function lastActivityByEmployee(): array
    {
        $rows = $this->select('employee_id, MAX(created_at) AS last_at')
            ->groupBy('employee_id')
            ->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['employee_id']] = (string) $r['last_at'];
        }
        return $out;
    }
}
