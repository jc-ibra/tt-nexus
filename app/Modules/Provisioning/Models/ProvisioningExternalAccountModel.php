<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Model;

class ProvisioningExternalAccountModel extends Model
{
    protected $table         = 'provisioning_external_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'employee_id', 'system_id', 'external_id', 'status',
        'last_message', 'last_sync_at',
    ];

    public function findFor(int $employeeId, int $systemId): ?array
    {
        return $this->where('employee_id', $employeeId)
            ->where('system_id', $systemId)
            ->first();
    }

    /**
     * GLPI user id linked to an employee, resolved by their employee number.
     * Returns null when there is no active GLPI account for that number, so
     * callers can fall back to a default requester.
     */
    public function glpiUserIdByEmployeeNumber(string $employeeNumber): ?int
    {
        $employeeNumber = trim($employeeNumber);
        if ($employeeNumber === '') {
            return null;
        }

        $row = $this->select('provisioning_external_accounts.external_id')
            ->join('provisioning_systems s', 's.id = provisioning_external_accounts.system_id', 'inner')
            ->join('employees_employees e', 'e.id = provisioning_external_accounts.employee_id', 'inner')
            ->where('s.key', 'glpi')
            ->where('e.employee_number', $employeeNumber)
            ->where('provisioning_external_accounts.status', 'active')
            ->where('provisioning_external_accounts.external_id IS NOT NULL')
            ->where('provisioning_external_accounts.external_id !=', '')
            ->first();

        $id = $row ? (int) $row['external_id'] : 0;
        return $id > 0 ? $id : null;
    }

    public function listForEmployee(int $employeeId): array
    {
        return $this->select('provisioning_external_accounts.*, s.key AS system_key, s.name AS system_name, s.is_active AS system_active')
            ->join('provisioning_systems s', 's.id = provisioning_external_accounts.system_id', 'left')
            ->where('employee_id', $employeeId)
            ->orderBy('s.name', 'ASC')
            ->findAll();
    }

    public function upsert(int $employeeId, int $systemId, array $data): void
    {
        $existing = $this->findFor($employeeId, $systemId);
        $payload  = array_merge([
            'employee_id'   => $employeeId,
            'system_id'     => $systemId,
            'last_sync_at'  => date('Y-m-d H:i:s'),
        ], $data);

        if ($existing) {
            $this->update($existing['id'], $payload);
        } else {
            $this->insert($payload);
        }
    }
}
