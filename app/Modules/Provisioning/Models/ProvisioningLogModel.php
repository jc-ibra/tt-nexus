<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Model;

class ProvisioningLogModel extends Model
{
    protected $table         = 'provisioning_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'employee_id', 'system_id', 'operation', 'status', 'message',
        'payload_summary', 'external_id', 'error_code',
        'executor_user_id', 'ip_address', 'created_at',
    ];

    public function record(array $data): int
    {
        $payload = array_merge([
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ], $data);

        $this->insert($payload);
        return (int) $this->getInsertID();
    }

    public function listForEmployee(int $employeeId, int $limit = 50): array
    {
        return $this->select('provisioning_log.*, s.name AS system_name, s.key AS system_key, u.name AS executor_name')
            ->join('provisioning_systems s', 's.id = provisioning_log.system_id', 'left')
            ->join('core_users u', 'u.id = provisioning_log.executor_user_id', 'left')
            ->where('employee_id', $employeeId)
            ->orderBy('provisioning_log.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function listRecent(array $filters = [], int $perPage = 50, int $page = 1): array
    {
        $builder = $this->select('provisioning_log.*, s.name AS system_name, s.key AS system_key, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number, u.name AS executor_name')
            ->join('provisioning_systems s', 's.id = provisioning_log.system_id', 'left')
            ->join('employees_employees e',            'e.id = provisioning_log.employee_id', 'left')
            ->join('core_users u',                'u.id = provisioning_log.executor_user_id', 'left');

        if (! empty($filters['operation'])) {
            $builder->where('provisioning_log.operation', $filters['operation']);
        }
        if (! empty($filters['status'])) {
            $builder->where('provisioning_log.status', $filters['status']);
        }
        if (! empty($filters['system_id'])) {
            $builder->where('provisioning_log.system_id', (int) $filters['system_id']);
        }
        if (! empty($filters['employee_id'])) {
            $builder->where('provisioning_log.employee_id', (int) $filters['employee_id']);
        }

        return $builder->orderBy('provisioning_log.created_at', 'DESC')
            ->paginate($perPage, 'default', $page);
    }
}
