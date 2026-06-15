<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Seeders;

use CodeIgniter\Database\Seeder;

class EmployeeDepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $names = ['Dirección General', 'Soporte Técnico', 'Desarrollo', 'Ventas', 'Contabilidad'];

        $inserted = 0;
        foreach ($names as $name) {
            $exists = $this->db->table('employees_departments')->where('name', $name)->get()->getRow();
            if ($exists) {
                continue;
            }

            $this->db->table('employees_departments')->insert([
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        echo "EmployeeDepartmentsSeeder: {$inserted} department(s) inserted.\n";
    }
}
