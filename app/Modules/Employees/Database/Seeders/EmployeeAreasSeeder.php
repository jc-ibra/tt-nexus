<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Seeders;

use CodeIgniter\Database\Seeder;

class EmployeeAreasSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $names = ['Administración', 'Operaciones', 'RRHH', 'TI', 'Comercial'];

        $inserted = 0;
        foreach ($names as $name) {
            $exists = $this->db->table('employee_areas')->where('name', $name)->get()->getRow();
            if ($exists) {
                continue;
            }

            $this->db->table('employee_areas')->insert([
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        echo "EmployeeAreasSeeder: {$inserted} area(s) inserted.\n";
    }
}
