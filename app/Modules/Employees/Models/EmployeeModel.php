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

    /**
     * Correlated subqueries flagging the email accounts an employee holds.
     *
     * An email account registered from the employee file is verified before it
     * is saved, so it is a real access from that moment on — even though
     * `has_mailbox` is only raised by the alta, the operational row in
     * `provisioning_external_accounts` only appears when the alta adopts the
     * mailbox, and a Microsoft account is never provisioned by Nexus at all.
     * Without these the directory reads "Sin accesos" for someone who already
     * has a cuenta. `$alias` is the alias of employees_employees (never user
     * input); `$type` is a fixed enum value, never a caller-supplied string.
     */
    private function emailAccountSubquery(string $type, string $column, string $alias = 'e'): string
    {
        return "EXISTS (SELECT 1 FROM employee_email_accounts ea_{$column}"
            . " WHERE ea_{$column}.employee_id = {$alias}.id AND ea_{$column}.type = '{$type}'"
            . " AND ea_{$column}.email IS NOT NULL AND ea_{$column}.email != '') AS has_{$column}_account";
    }

    public function paginateWithFilters(array $filters, int $perPage = 20, int $page = 1): array
    {
        $offset  = ($page - 1) * $perPage;
        $builder = $this->db->table('employees_employees e')
            ->select('e.id, e.employee_number, e.name, e.lastname, e.email, e.has_mailbox, e.photo, e.active, e.date_entry, a.name AS area_name, d.name AS department_name, p.name AS position_name')
            ->select($this->primaryEmailSubquery(), false)
            ->select($this->emailAccountSubquery('mailcow', 'mailcow'), false)
            ->select($this->emailAccountSubquery('microsoft', 'microsoft'), false)
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
            ->select($this->emailAccountSubquery('mailcow', 'mailcow'), false)
            ->select($this->emailAccountSubquery('microsoft', 'microsoft'), false)
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

    // -----------------------------------------------------------------------
    // Aggregates for the RRHH dashboard. All read-only: they only SELECT and
    // never touch a row. Every one of them scopes to `deleted_at IS NULL`.
    // -----------------------------------------------------------------------

    /**
     * Catalog dimensions the dashboard can group head-count by. The FK column
     * and the catalog table are looked up here and never taken from the
     * request, so the identifiers interpolated into the query are always ours.
     */
    private const HEADCOUNT_DIMENSIONS = [
        'area'       => ['fk' => 'area_id',       'table' => 'employees_areas',       'empty' => 'Sin área'],
        'department' => ['fk' => 'department_id', 'table' => 'employees_departments', 'empty' => 'Sin departamento'],
        'position'   => ['fk' => 'position_id',   'table' => 'employees_positions',   'empty' => 'Sin puesto'],
        'state'      => ['fk' => 'state_id',      'table' => 'employees_states',      'empty' => 'Sin estado'],
        'location'   => ['fk' => 'location_id',   'table' => 'employees_locations',   'empty' => 'Sin ubicación'],
    ];

    /**
     * Head-count grouped by one catalog dimension, biggest bucket first.
     * Employees with no value for the dimension collapse into a single bucket
     * with a null id, so the total always adds up to the whole population.
     *
     * @return list<array{id:int|null,name:string,total:int}>
     */
    public function headcountByDimension(string $dimension, bool $activeOnly = true): array
    {
        $dim = self::HEADCOUNT_DIMENSIONS[$dimension] ?? null;

        if ($dim === null) {
            throw new \InvalidArgumentException("Dimensión de plantilla no soportada: {$dimension}");
        }

        $builder = $this->db->table('employees_employees e')
            ->select('c.id AS catalog_id, c.name AS catalog_name, COUNT(e.id) AS total', false)
            ->join("{$dim['table']} c", "c.id = e.{$dim['fk']}", 'left')
            ->where('e.deleted_at', null);

        if ($activeOnly) {
            $builder->where('e.active', 1);
        }

        $rows = $builder->groupBy(['c.id', 'c.name'])
            ->orderBy('total', 'DESC')
            ->orderBy('c.name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn(array $r): array => [
            'id'    => $r['catalog_id'] !== null ? (int) $r['catalog_id'] : null,
            'name'  => $r['catalog_name'] ?? $dim['empty'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * Headline numbers of the whole directory, resolved in one aggregate query.
     *
     * @return array{total:int,active:int,inactive:int,hires_12m:int,exits_12m:int,avg_tenure_months:float,areas:int,departments:int,positions:int,states:int,locations:int}
     */
    public function headcountSummary(): array
    {
        $row = $this->db->table('employees_employees e')
            ->select('COUNT(*) AS total', false)
            ->select('SUM(CASE WHEN e.active = 1 THEN 1 ELSE 0 END) AS active_count', false)
            ->select('SUM(CASE WHEN e.active = 0 THEN 1 ELSE 0 END) AS inactive_count', false)
            ->select('SUM(CASE WHEN e.date_entry IS NOT NULL AND e.date_entry > DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND e.date_entry <= CURDATE() THEN 1 ELSE 0 END) AS hires_12m', false)
            ->select('SUM(CASE WHEN e.date_discharge IS NOT NULL AND e.date_discharge > DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND e.date_discharge <= CURDATE() THEN 1 ELSE 0 END) AS exits_12m', false)
            ->select('AVG(CASE WHEN e.active = 1 AND e.date_entry IS NOT NULL AND e.date_entry <= CURDATE() THEN TIMESTAMPDIFF(MONTH, e.date_entry, CURDATE()) END) AS avg_tenure_months', false)
            ->select('COUNT(DISTINCT CASE WHEN e.active = 1 THEN e.area_id END) AS areas_count', false)
            ->select('COUNT(DISTINCT CASE WHEN e.active = 1 THEN e.department_id END) AS departments_count', false)
            ->select('COUNT(DISTINCT CASE WHEN e.active = 1 THEN e.position_id END) AS positions_count', false)
            ->select('COUNT(DISTINCT CASE WHEN e.active = 1 THEN e.state_id END) AS states_count', false)
            ->select('COUNT(DISTINCT CASE WHEN e.active = 1 THEN e.location_id END) AS locations_count', false)
            ->where('e.deleted_at', null)
            ->get()->getRowArray() ?: [];

        return [
            'total'             => (int) ($row['total'] ?? 0),
            'active'            => (int) ($row['active_count'] ?? 0),
            'inactive'          => (int) ($row['inactive_count'] ?? 0),
            'hires_12m'         => (int) ($row['hires_12m'] ?? 0),
            'exits_12m'         => (int) ($row['exits_12m'] ?? 0),
            'avg_tenure_months' => round((float) ($row['avg_tenure_months'] ?? 0), 1),
            'areas'             => (int) ($row['areas_count'] ?? 0),
            'departments'       => (int) ($row['departments_count'] ?? 0),
            'positions'         => (int) ($row['positions_count'] ?? 0),
            'states'            => (int) ($row['states_count'] ?? 0),
            'locations'         => (int) ($row['locations_count'] ?? 0),
        ];
    }

    /**
     * Tenure of the active head-count, bucketed into the ranges RRHH reads.
     * Employees with no `date_entry` land in their own bucket instead of being
     * silently dropped, so the buckets still add up to the active population.
     *
     * @return list<array{label:string,total:int}>
     */
    public function tenureBuckets(): array
    {
        $months = 'TIMESTAMPDIFF(MONTH, e.date_entry, CURDATE())';

        $row = $this->db->table('employees_employees e')
            ->select("SUM(CASE WHEN e.date_entry IS NOT NULL AND {$months} < 12 THEN 1 ELSE 0 END) AS b_lt1", false)
            ->select("SUM(CASE WHEN e.date_entry IS NOT NULL AND {$months} >= 12 AND {$months} < 36 THEN 1 ELSE 0 END) AS b_1_3", false)
            ->select("SUM(CASE WHEN e.date_entry IS NOT NULL AND {$months} >= 36 AND {$months} < 60 THEN 1 ELSE 0 END) AS b_3_5", false)
            ->select("SUM(CASE WHEN e.date_entry IS NOT NULL AND {$months} >= 60 AND {$months} < 120 THEN 1 ELSE 0 END) AS b_5_10", false)
            ->select("SUM(CASE WHEN e.date_entry IS NOT NULL AND {$months} >= 120 THEN 1 ELSE 0 END) AS b_gte10", false)
            ->select('SUM(CASE WHEN e.date_entry IS NULL THEN 1 ELSE 0 END) AS b_unknown', false)
            ->where('e.deleted_at', null)
            ->where('e.active', 1)
            ->get()->getRowArray() ?: [];

        $labels = [
            'b_lt1'     => 'Menos de 1 año',
            'b_1_3'     => 'De 1 a 3 años',
            'b_3_5'     => 'De 3 a 5 años',
            'b_5_10'    => 'De 5 a 10 años',
            'b_gte10'   => 'Más de 10 años',
            'b_unknown' => 'Sin fecha de ingreso',
        ];

        $buckets = [];
        foreach ($labels as $key => $label) {
            $buckets[] = ['label' => $label, 'total' => (int) ($row[$key] ?? 0)];
        }

        return $buckets;
    }

    /**
     * Hires and exits per calendar month for the last `$months` months. The
     * skeleton is built in PHP so months with no movement come back as zeros
     * instead of disappearing from the series.
     *
     * @return list<array{month:string,hires:int,exits:int}>
     */
    public function movementsByMonth(int $months = 12): array
    {
        $months = max(1, min(36, $months));

        $firstOfThisMonth = new \DateTimeImmutable('first day of this month');
        $from             = $firstOfThisMonth->modify('-' . ($months - 1) . ' month');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $key          = $from->modify("+{$i} month")->format('Y-m');
            $series[$key] = ['month' => $key, 'hires' => 0, 'exits' => 0];
        }

        $fromDate = $from->format('Y-m-d');

        foreach (['date_entry' => 'hires', 'date_discharge' => 'exits'] as $column => $bucket) {
            $rows = $this->db->table('employees_employees')
                ->select("DATE_FORMAT({$column}, '%Y-%m') AS ym, COUNT(*) AS total", false)
                ->where('deleted_at', null)
                ->where("{$column} >=", $fromDate)
                ->where("{$column} <= CURDATE()", null, false)
                ->groupBy('ym')
                ->get()->getResultArray();

            foreach ($rows as $r) {
                $ym = (string) $r['ym'];
                if (isset($series[$ym])) {
                    $series[$ym][$bucket] = (int) $r['total'];
                }
            }
        }

        return array_values($series);
    }

    /**
     * Managers ranked by how many active people report to them (span of
     * control). Managers themselves must still exist and not be soft-deleted.
     *
     * @return list<array{id:int,name:string,total:int}>
     */
    public function spanOfControl(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $rows = $this->db->table('employees_employees e')
            ->select("m.id AS manager_id, TRIM(CONCAT(m.name, ' ', COALESCE(m.lastname, ''))) AS manager_name, COUNT(e.id) AS total", false)
            ->join('employees_employees m', 'm.id = e.parent_id', 'inner')
            ->where('e.deleted_at', null)
            ->where('e.active', 1)
            ->where('m.deleted_at', null)
            ->groupBy(['m.id', 'm.name', 'm.lastname'])
            ->orderBy('total', 'DESC')
            ->orderBy('manager_name', 'ASC')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(static fn(array $r): array => [
            'id'    => (int) $r['manager_id'],
            'name'  => (string) $r['manager_name'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * Active employees missing each catalog assignment, so RRHH can see how
     * much of the directory is still incomplete.
     *
     * @return array<string,int>
     */
    public function missingDataCounts(): array
    {
        $row = $this->db->table('employees_employees e')
            ->select('SUM(CASE WHEN e.area_id IS NULL THEN 1 ELSE 0 END) AS no_area', false)
            ->select('SUM(CASE WHEN e.department_id IS NULL THEN 1 ELSE 0 END) AS no_department', false)
            ->select('SUM(CASE WHEN e.position_id IS NULL THEN 1 ELSE 0 END) AS no_position', false)
            ->select('SUM(CASE WHEN e.state_id IS NULL THEN 1 ELSE 0 END) AS no_state', false)
            ->select('SUM(CASE WHEN e.location_id IS NULL THEN 1 ELSE 0 END) AS no_location', false)
            ->select('SUM(CASE WHEN e.date_entry IS NULL THEN 1 ELSE 0 END) AS no_date_entry', false)
            ->select('SUM(CASE WHEN e.parent_id IS NULL THEN 1 ELSE 0 END) AS no_manager', false)
            ->where('e.deleted_at', null)
            ->where('e.active', 1)
            ->get()->getRowArray() ?: [];

        return [
            'no_area'       => (int) ($row['no_area'] ?? 0),
            'no_department' => (int) ($row['no_department'] ?? 0),
            'no_position'   => (int) ($row['no_position'] ?? 0),
            'no_state'      => (int) ($row['no_state'] ?? 0),
            'no_location'   => (int) ($row['no_location'] ?? 0),
            'no_date_entry' => (int) ($row['no_date_entry'] ?? 0),
            'no_manager'    => (int) ($row['no_manager'] ?? 0),
        ];
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
