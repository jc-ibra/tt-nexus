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
        'employee_id', 'system_id', 'external_id', 'status', 'origin',
        'last_message', 'last_sync_at',
    ];

    public function findFor(int $employeeId, int $systemId): ?array
    {
        return $this->where('employee_id', $employeeId)
            ->where('system_id', $systemId)
            ->first();
    }

    /**
     * The employee (other than $exceptEmployeeId) already holding this external
     * account on the given system, or null when the account is free.
     *
     * An external user is one person: linking the same GLPI user to two
     * employees would make a baja or a password change act on the wrong file.
     * The (employee_id, system_id) unique key cannot catch this — it guards the
     * opposite direction — so this is the check that keeps mapping honest.
     */
    public function findLinkedToOtherEmployee(int $systemId, string $externalId, int $exceptEmployeeId): ?array
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }

        $sql = 'SELECT a.id, a.employee_id, a.status, a.origin,
                       e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number
                FROM provisioning_external_accounts a
                LEFT JOIN employees_employees e ON e.id = a.employee_id
                WHERE a.system_id = ? AND a.external_id = ? AND a.employee_id != ?
                LIMIT 1';

        return $this->db->query($sql, [$systemId, $externalId, $exceptEmployeeId])->getRowArray() ?: null;
    }

    /**
     * external_id => owner label, for the external accounts among $externalIds
     * that belong to an employee other than $exceptEmployeeId.
     *
     * Lets a search result mark the candidates that are already taken in a
     * single query instead of one lookup per row.
     *
     * @param array<int,string|int> $externalIds
     * @return array<string,string>
     */
    public function mapOwnersForExternalIds(int $systemId, array $externalIds, int $exceptEmployeeId): array
    {
        $externalIds = array_values(array_unique(array_filter(
            array_map(fn($id) => trim((string) $id), $externalIds),
            fn(string $id) => $id !== '',
        )));
        if ($externalIds === []) {
            return [];
        }

        $rows = $this->select('provisioning_external_accounts.external_id, e.name, e.lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = provisioning_external_accounts.employee_id', 'left')
            ->where('provisioning_external_accounts.system_id', $systemId)
            ->where('provisioning_external_accounts.employee_id !=', $exceptEmployeeId)
            ->whereIn('provisioning_external_accounts.external_id', $externalIds)
            ->findAll();

        $map = [];
        foreach ($rows as $r) {
            $who = trim(($r['name'] ?? '') . ' ' . ($r['lastname'] ?? ''));
            $num = trim((string) ($r['employee_number'] ?? ''));
            $map[(string) $r['external_id']] = $who !== ''
                ? $who . ($num !== '' ? " (#{$num})" : '')
                : 'otro empleado';
        }

        return $map;
    }

    /**
     * Drops the employee's row for a system. Only removes Nexus' mapping: the
     * account in the external system is never touched.
     */
    public function removeFor(int $employeeId, int $systemId): bool
    {
        $existing = $this->findFor($employeeId, $systemId);
        if (! $existing) {
            return false;
        }
        return (bool) $this->delete($existing['id']);
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

    /**
     * Accounts for many employees at once, grouped by employee_id. Lets the
     * employee directory render the "Accesos" column without an N+1 query.
     *
     * @param int[] $employeeIds
     * @return array<int, array<int, array<string,mixed>>>
     */
    public function mapForEmployees(array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        if ($employeeIds === []) {
            return [];
        }

        $rows = $this->select('provisioning_external_accounts.employee_id, provisioning_external_accounts.status, provisioning_external_accounts.external_id, s.key AS system_key, s.name AS system_name')
            ->join('provisioning_systems s', 's.id = provisioning_external_accounts.system_id', 'left')
            ->whereIn('provisioning_external_accounts.employee_id', $employeeIds)
            ->orderBy('s.name', 'ASC')
            ->findAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['employee_id']][] = $r;
        }

        return $map;
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
