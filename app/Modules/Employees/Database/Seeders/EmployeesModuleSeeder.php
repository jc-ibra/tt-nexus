<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Seeders;

use CodeIgniter\Database\Seeder;

class EmployeesModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('modules')->where('key', 'employees')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "EmployeesModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('modules')->insert([
                'key'         => 'employees',
                'name'        => 'Empleados',
                'description' => 'Directorio de empleados con áreas, departamentos y puestos.',
                'route_base'  => 'empleados',
                'icon'        => 'users',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "EmployeesModuleSeeder: module created (id={$moduleId}).\n";
        }

        $superAdmin = $this->db->table('roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $link = $this->db->table('role_module')
                ->where('role_id', $superAdmin->id)
                ->where('module_id', $moduleId)
                ->get()->getRow();

            if (! $link) {
                $this->db->table('role_module')->insert([
                    'role_id'   => $superAdmin->id,
                    'module_id' => $moduleId,
                ]);
                echo "EmployeesModuleSeeder: granted access to SuperAdmin.\n";
            }
        }
    }
}
