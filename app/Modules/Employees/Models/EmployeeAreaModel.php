<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeAreaModel extends Model
{
    protected $table         = 'employee_areas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['name', 'status'];

    protected $validationRules = [
        'name'   => 'required|max_length[120]|is_unique[employee_areas.name,id,{id}]',
        'status' => 'in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'El nombre del área es obligatorio.',
            'is_unique'  => 'Ya existe un área con ese nombre.',
            'max_length' => 'El nombre no puede exceder 120 caracteres.',
        ],
    ];

    public function getAllActive(): array
    {
        return $this->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }

    public function countEmployees(int $id): int
    {
        return $this->db->table('employees')
            ->where('area_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();
    }
}
