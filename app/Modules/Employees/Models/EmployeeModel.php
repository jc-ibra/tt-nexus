<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table            = 'employees_employees';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    protected $allowedFields = [
        'employee_number', 'name', 'lastname', 'email', 'email_secondary',
        'has_mailbox', 'photo', 'telephone', 'cellphone', 'ext',
        'position_id', 'department_id', 'area_id', 'state_id', 'location_id', 'parent_id',
        'date_entry', 'date_discharge',
        'hide_emails', 'show_in_directory', 'active',
    ];

    protected $validationRules = [
        // Required so CI4 can substitute the {id} placeholder used by the
        // is_unique[...,id,{id}] rules on email/employee_number during update.
        'id'              => 'permit_empty|is_natural_no_zero',
        'name'            => 'required|max_length[180]',
        'lastname'        => 'required|max_length[255]',
        'email'           => 'required|valid_email|max_length[191]|is_unique[employees_employees.email,id,{id}]',
        'email_secondary' => 'permit_empty|valid_email|max_length[255]',
        'employee_number' => 'required|max_length[20]|is_unique[employees_employees.employee_number,id,{id}]',
        'telephone'       => 'permit_empty|max_length[15]',
        'cellphone'       => 'permit_empty|max_length[20]',
        'ext'             => 'permit_empty|max_length[20]',
        'position_id'     => 'required|integer|is_not_unique[employees_positions.id]',
        'department_id'   => 'required|integer|is_not_unique[employees_departments.id]',
        'area_id'         => 'required|integer|is_not_unique[employees_areas.id]',
        'state_id'        => 'required|integer|is_not_unique[employees_states.id]',
        'location_id'     => 'permit_empty|integer|is_not_unique[employees_locations.id]',
        'parent_id'       => 'permit_empty|integer|is_not_unique[employees_employees.id]',
        'date_entry'      => 'required|valid_date',
        'date_discharge'  => 'permit_empty|valid_date',
    ];

    protected $validationMessages = [
        'name'     => ['required' => 'El nombre es obligatorio.'],
        'lastname' => ['required' => 'Los apellidos son obligatorios.'],
        'email' => [
            'required'    => 'El correo electrónico es obligatorio.',
            'valid_email' => 'El correo electrónico no es válido.',
            'is_unique'   => 'Ya existe un empleado con ese correo.',
        ],
        'employee_number' => [
            'required'  => 'El número de empleado es obligatorio.',
            'is_unique' => 'Ya existe un empleado con ese número.',
        ],
        'position_id'   => [
            'required'      => 'El puesto es obligatorio.',
            'is_not_unique' => 'El puesto seleccionado no existe.',
        ],
        'department_id' => [
            'required'      => 'El departamento es obligatorio.',
            'is_not_unique' => 'El departamento seleccionado no existe.',
        ],
        'area_id'       => [
            'required'      => 'El área es obligatoria.',
            'is_not_unique' => 'El área seleccionada no existe.',
        ],
        'state_id'      => [
            'required'      => 'El estado de origen es obligatorio.',
            'is_not_unique' => 'El estado seleccionado no existe.',
        ],
        'location_id'   => [
            'required'      => 'La ubicación de origen es obligatoria.',
            'is_not_unique' => 'La ubicación seleccionada no existe.',
        ],
        'date_entry'    => ['required' => 'La fecha de ingreso es obligatoria.'],
        'parent_id'     => ['is_not_unique' => 'El jefe directo seleccionado no existe.'],
    ];

    public function findWithRelations(int $id): ?array
    {
        $row = $this->db->table('employees_employees e')
            ->select('e.*, a.name AS area_name, d.name AS department_name, p.name AS position_name, st.name AS state_name, loc.name AS location_name, parent.name AS parent_name, parent.lastname AS parent_lastname')
            ->join('employees_areas a',       'a.id = e.area_id',       'left')
            ->join('employees_departments d', 'd.id = e.department_id', 'left')
            ->join('employees_positions p',   'p.id = e.position_id',   'left')
            ->join('employees_states st',     'st.id = e.state_id',     'left')
            ->join('employees_locations loc', 'loc.id = e.location_id', 'left')
            ->join('employees_employees parent',       'parent.id = e.parent_id', 'left')
            ->where('e.id', $id)
            ->where('e.deleted_at', null)
            ->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Correlated subquery resolving the employee's primary account email — the
     * account flagged `is_primary` in employee_email_accounts. `$alias` is the
     * alias given to employees_employees in the outer query (never user input).
     */
    private function primaryEmailSubquery(string $alias = 'e'): string
    {
        return "(SELECT ea.email FROM employee_email_accounts ea"
            . " WHERE ea.employee_id = {$alias}.id AND ea.is_primary = 1"
            . " AND ea.email IS NOT NULL AND ea.email != ''"
            . " ORDER BY ea.id ASC LIMIT 1) AS primary_email";
    }

    public function paginateWithFilters(array $filters, int $perPage = 20, int $page = 1): array
    {
        $offset  = ($page - 1) * $perPage;
        $builder = $this->db->table('employees_employees e')
            ->select('e.id, e.employee_number, e.name, e.lastname, e.email, e.has_mailbox, e.photo, e.active, e.date_entry, a.name AS area_name, d.name AS department_name, p.name AS position_name')
            ->select($this->primaryEmailSubquery(), false)
            ->join('employees_areas a',       'a.id = e.area_id',       'left')
            ->join('employees_departments d', 'd.id = e.department_id', 'left')
            ->join('employees_positions p',   'p.id = e.position_id',   'left')
            ->where('e.deleted_at', null);

        $this->applyFilters($builder, $filters);

        $rows = $builder->orderBy('e.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()->getResultArray();

        return $rows;
    }

    /**
     * Every row matching the filters, unpaginated, for the directory export.
     * Deliberately mirrors the visible columns of the index table (no photo and
     * no personal contact data) — the export must not widen what the screen
     * already exposes.
     */
    public function listAllForExport(array $filters): array
    {
        $builder = $this->db->table('employees_employees e')
            ->select('e.id, e.employee_number, e.name, e.lastname, e.has_mailbox, e.active, a.name AS area_name, d.name AS department_name, p.name AS position_name')
            ->select($this->primaryEmailSubquery(), false)
            ->join('employees_areas a',       'a.id = e.area_id',       'left')
            ->join('employees_departments d', 'd.id = e.department_id', 'left')
            ->join('employees_positions p',   'p.id = e.position_id',   'left')
            ->where('e.deleted_at', null);

        $this->applyFilters($builder, $filters);

        return $builder->orderBy('e.name', 'ASC')->get()->getResultArray();
    }

    /**
     * Headline counts for the directory summary, resolved in a single aggregate
     * query. Callers pass the active filters WITHOUT `active`, so the breakdown
     * always segments the same population: filtering by "Activos" must not zero
     * out the "Inactivos" card.
     *
     * @return array{total:int,active:int,inactive:int}
     */
    public function statsWithFilters(array $filters): array
    {
        $builder = $this->db->table('employees_employees e')
            ->select('COUNT(*) AS total', false)
            ->select('SUM(CASE WHEN e.active = 1 THEN 1 ELSE 0 END) AS active_count', false)
            ->select('SUM(CASE WHEN e.active = 0 THEN 1 ELSE 0 END) AS inactive_count', false)
            ->where('e.deleted_at', null);

        $this->applyFilters($builder, $filters);

        $row = $builder->get()->getRowArray() ?: [];

        return [
            'total'    => (int) ($row['total'] ?? 0),
            'active'   => (int) ($row['active_count'] ?? 0),
            'inactive' => (int) ($row['inactive_count'] ?? 0),
        ];
    }

    public function countWithFilters(array $filters): int
    {
        $builder = $this->db->table('employees_employees e')
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

    /**
     * Direct reports, each carrying `primary_email` when the employee already
     * has an account flagged as primary, so the profile can show the
     * institutional address instead of the personal one captured by RRHH.
     */
    public function directReports(int $employeeId): array
    {
        return $this->db->table('employees_employees e')
            ->select('e.id, e.name, e.lastname, e.email, e.active')
            ->select($this->primaryEmailSubquery(), false)
            ->where('e.parent_id', $employeeId)
            ->where('e.deleted_at', null)
            ->orderBy('e.name', 'ASC')
            ->get()->getResultArray();
    }

    public function search(string $term, int $limit = 20, ?int $excludeId = null): array
    {
        $builder = $this->db->table('employees_employees')
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
