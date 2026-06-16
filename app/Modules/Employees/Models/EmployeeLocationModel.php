<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeLocationModel extends Model
{
    protected $table         = 'employees_locations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['name', 'status'];

    protected $validationRules = [
        'name'   => 'required|max_length[120]|is_unique[employees_locations.name,id,{id}]',
        'status' => 'in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'El nombre de la ubicación es obligatorio.',
            'is_unique'  => 'Ya existe una ubicación con ese nombre.',
            'max_length' => 'El nombre no puede exceder 120 caracteres.',
        ],
    ];

    public function getAllActive(): array
    {
        return $this->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }

    public function countEmployees(int $id): int
    {
        return $this->db->table('employees_employees')
            ->where('location_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();
    }
}
