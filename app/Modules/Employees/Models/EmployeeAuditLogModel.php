<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeAuditLogModel extends Model
{
    protected $table         = 'employees_audit_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'employee_id', 'event_id', 'action', 'field', 'old_value', 'new_value',
        'actor_user_id', 'actor_name', 'source', 'ip_address', 'created_at',
    ];

    /**
     * Inserts every row of one audit event (one save may touch several
     * fields) in a single batch. Rows already carry `created_at` — set once
     * per event by EmployeeAuditService so all of an event's rows share the
     * exact same timestamp.
     */
    public function record(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->insertBatch($rows);
    }

    public function listForEmployee(int $employeeId, int $limit = 100): array
    {
        return $this->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function listRecent(array $filters = [], int $perPage = 50, int $page = 1): array
    {
        $builder = $this->baseQuery($filters);

        return $builder->orderBy('employees_audit_log.created_at', 'DESC')
            ->orderBy('employees_audit_log.id', 'DESC')
            ->paginate($perPage, 'default', $page);
    }

    public function countWithFilters(array $filters = []): int
    {
        return $this->baseQuery($filters)->countAllResults();
    }

    /**
     * Same joins and filters as listRecent() but without pagination — used
     * for CSV export.
     */
    public function listAllForExport(array $filters = []): array
    {
        $builder = $this->baseQuery($filters);

        return $builder->orderBy('employees_audit_log.created_at', 'DESC')
            ->orderBy('employees_audit_log.id', 'DESC')
            ->findAll();
    }

    /**
     * Actors who have at least one entry, for the "Usuario" filter select.
     */
    public function distinctActors(): array
    {
        return $this->select('actor_user_id, actor_name')
            ->where('actor_user_id IS NOT NULL')
            ->groupBy(['actor_user_id', 'actor_name'])
            ->orderBy('actor_name', 'ASC')
            ->findAll();
    }

    private function baseQuery(array $filters): self
    {
        $builder = $this->select('employees_audit_log.*, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = employees_audit_log.employee_id', 'left');

        if (! empty($filters['employee_id'])) {
            $builder->where('employees_audit_log.employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['actor_user_id'])) {
            $builder->where('employees_audit_log.actor_user_id', (int) $filters['actor_user_id']);
        }
        if (! empty($filters['action'])) {
            $builder->where('employees_audit_log.action', $filters['action']);
        }
        if (! empty($filters['field'])) {
            $builder->where('employees_audit_log.field', $filters['field']);
        }
        if (! empty($filters['date_from'])) {
            $builder->where('employees_audit_log.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $builder->where('employees_audit_log.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $builder->groupStart()
                ->like('e.name', $term)
                ->orLike('e.lastname', $term)
                ->orLike('e.employee_number', $term)
                ->groupEnd();
        }

        return $builder;
    }
}
