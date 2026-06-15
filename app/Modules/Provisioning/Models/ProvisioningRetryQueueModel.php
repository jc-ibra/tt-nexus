<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Model;

class ProvisioningRetryQueueModel extends Model
{
    protected $table         = 'provisioning_retry_queue';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'log_id', 'employee_id', 'system_id', 'operation', 'payload',
        'attempts', 'max_attempts', 'next_attempt_at', 'last_error', 'status',
    ];

    public function enqueue(int $logId, int $employeeId, int $systemId, string $operation, array $payload, int $delaySeconds = 60): int
    {
        $this->insert([
            'log_id'          => $logId,
            'employee_id'     => $employeeId,
            'system_id'       => $systemId,
            'operation'       => $operation,
            'payload'         => json_encode($payload),
            'attempts'        => 0,
            'max_attempts'    => 5,
            'next_attempt_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
            'status'          => 'pending',
        ]);

        return (int) $this->getInsertID();
    }

    public function nextDue(int $limit = 25): array
    {
        return $this->where('status', 'pending')
            ->groupStart()
                ->where('next_attempt_at IS NULL', null, false)
                ->orWhere('next_attempt_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('next_attempt_at', 'ASC')
            ->limit($limit)
            ->findAll();
    }

    public function listAll(int $limit = 100): array
    {
        return $this->select('provisioning_retry_queue.*, s.name AS system_name, e.name AS employee_name, e.lastname AS employee_lastname')
            ->join('provisioning_systems s', 's.id = provisioning_retry_queue.system_id', 'left')
            ->join('employees_employees e',            'e.id = provisioning_retry_queue.employee_id', 'left')
            ->orderBy('provisioning_retry_queue.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function markSuccess(int $id): void
    {
        $this->update($id, ['status' => 'completed']);
    }

    public function markFailure(int $id, string $error, int $attempts, int $maxAttempts): void
    {
        $abandoned = $attempts >= $maxAttempts;
        $backoff   = min(3600, 60 * (2 ** $attempts));

        $this->update($id, [
            'attempts'        => $attempts,
            'last_error'      => $error,
            'next_attempt_at' => date('Y-m-d H:i:s', time() + $backoff),
            'status'          => $abandoned ? 'abandoned' : 'pending',
        ]);
    }
}
