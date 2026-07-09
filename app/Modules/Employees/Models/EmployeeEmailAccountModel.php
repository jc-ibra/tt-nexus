<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeEmailAccountModel extends Model
{
    protected $table         = 'employee_email_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['employee_id', 'type', 'email', 'ms_license_id', 'is_primary', 'notes', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    public function listForEmployee(int $employeeId): array
    {
        return $this->db->table('employee_email_accounts a')
            ->select('a.*, l.name AS license_name, l.code AS license_code')
            ->join('provisioning_ms_licenses l', 'l.id = a.ms_license_id', 'left')
            ->where('a.employee_id', $employeeId)
            ->orderBy('a.is_primary', 'DESC')
            ->orderBy('a.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function countForEmployee(int $employeeId): int
    {
        return (int) $this->where('employee_id', $employeeId)->countAllResults();
    }

    public function addAccount(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }

    public function findForEmployee(int $id, int $employeeId): ?array
    {
        return $this->where('id', $id)->where('employee_id', $employeeId)->first();
    }

    public function updateAccount(int $id, int $employeeId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->table($this->table)
            ->where('id', $id)
            ->where('employee_id', $employeeId)
            ->update($data);
    }

    /**
     * Clears the primary flag from all of the employee's accounts, optionally
     * keeping one (the account that should remain/become primary). Enforces the
     * "only one primary account" rule.
     */
    public function clearPrimary(int $employeeId, ?int $exceptId = null): void
    {
        $builder = $this->db->table($this->table)
            ->where('employee_id', $employeeId)
            ->where('is_primary', 1);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        $builder->update(['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function deleteAccount(int $id, int $employeeId): bool
    {
        return $this->where('id', $id)->where('employee_id', $employeeId)->delete() > 0;
    }
}
