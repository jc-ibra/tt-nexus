<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table            = 'employees';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    protected $allowedFields = [
        'employee_number', 'name', 'lastname', 'email', 'email_secondary',
        'has_mailbox', 'photo', 'telephone', 'cellphone', 'ext',
        'position_id', 'department_id', 'area_id', 'parent_id',
        'date_entry', 'date_discharge',
        'hide_emails', 'show_in_directory', 'active',
    ];

    protected $validationRules = [
        'name'            => 'required|max_length[180]',
        'lastname'        => 'permit_empty|max_length[255]',
        'email'           => 'required|valid_email|max_length[191]|is_unique[employees.email,id,{id}]',
        'email_secondary' => 'permit_empty|valid_email|max_length[255]',
        'employee_number' => 'permit_empty|max_length[20]',
        'telephone'       => 'permit_empty|max_length[15]',
        'cellphone'       => 'permit_empty|max_length[20]',
        'ext'             => 'permit_empty|max_length[20]',
        'position_id'     => 'permit_empty|integer|is_not_unique[employee_positions.id]',
        'department_id'   => 'permit_empty|integer|is_not_unique[employee_departments.id]',
        'area_id'         => 'permit_empty|integer|is_not_unique[employee_areas.id]',
        'parent_id'       => 'permit_empty|integer|is_not_unique[employees.id]',
        'date_entry'      => 'permit_empty|valid_date',
        'date_discharge'  => 'permit_empty|valid_date',
    ];

    protected $validationMessages = [
        'name'  => ['required' => 'El nombre es obligatorio.'],
        'email' => [
            'required'    => 'El correo electrónico es obligatorio.',
            'valid_email' => 'El correo electrónico no es válido.',
            'is_unique'   => 'Ya existe un empleado con ese correo.',
        ],
        'position_id'   => ['is_not_unique' => 'El puesto seleccionado no existe.'],
        'department_id' => ['is_not_unique' => 'El departamento seleccionado no existe.'],
        'area_id'       => ['is_not_unique' => 'El área seleccionada no existe.'],
        'parent_id'     => ['is_not_unique' => 'El jefe directo seleccionado no existe.'],
    ];

    public function findWithRelations(int $id): ?array
    {
        $row = $this->db->table('employees e')
            ->select('e.*, a.name AS area_name, d.name AS department_name, p.name AS position_name, parent.name AS parent_name, parent.lastname AS parent_lastname')
            ->join('employee_areas a',       'a.id = e.area_id',       'left')
            ->join('employee_departments d', 'd.id = e.department_id', 'left')
            ->join('employee_positions p',   'p.id = e.position_id',   'left')
            ->join('employees parent',       'parent.id = e.parent_id', 'left')
            ->where('e.id', $id)
            ->where('e.deleted_at', null)
            ->get()->getRowArray();

        return $row ?: null;
    }

    public function paginateWithFilters(array $filters, int $perPage = 20, int $page = 1): array
    {
        $offset  = ($page - 1) * $perPage;
        $builder = $this->db->table('employees e')
            ->select('e.id, e.employee_number, e.name, e.lastname, e.email, e.has_mailbox, e.photo, e.active, e.date_entry, a.name AS area_name, d.name AS department_name, p.name AS position_name')
            ->join('employee_areas a',       'a.id = e.area_id',       'left')
            ->join('employee_departments d', 'd.id = e.department_id', 'left')
            ->join('employee_positions p',   'p.id = e.position_id',   'left')
            ->where('e.deleted_at', null);

        $this->applyFilters($builder, $filters);

        $rows = $builder->orderBy('e.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()->getResultArray();

        return $rows;
    }

    public function countWithFilters(array $filters): int
    {
        $builder = $this->db->table('employees e')
            ->where('e.deleted_at', null);

        $this->applyFilters($builder, $filters);

        return $builder->countAllResults();
    }

    private function applyFilters(\CodeIgniter\Database\BaseBuilder $builder, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $builder->groupStart()
                ->like('e.name', $term)
                ->orLike('e.lastname', $term)
                ->orLike('e.email', $term)
                ->orLike('e.employee_number', $term)
                ->groupEnd();
        }

        if (! empty($filters['area_id'])) {
            $builder->where('e.area_id', (int) $filters['area_id']);
        }

        if (! empty($filters['department_id'])) {
            $builder->where('e.department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['position_id'])) {
            $builder->where('e.position_id', (int) $filters['position_id']);
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $builder->where('e.active', (int) $filters['active']);
        }
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    public function directReports(int $employeeId): array
    {
        return $this->where('parent_id', $employeeId)
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function search(string $term, int $limit = 20, ?int $excludeId = null): array
    {
        $builder = $this->db->table('employees')
            ->select('id, name, lastname, email, employee_number')
            ->where('deleted_at', null)
            ->groupStart()
                ->like('name', $term)
                ->orLike('lastname', $term)
                ->orLike('email', $term)
                ->orLike('employee_number', $term)
            ->groupEnd();

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->orderBy('name', 'ASC')->limit($limit)->get()->getResultArray();
    }
}
