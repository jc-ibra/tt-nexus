<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Seeders;

use CodeIgniter\Database\Seeder;

class EmployeePositionsSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $names = ['Director', 'Gerente', 'Coordinador', 'Analista', 'Asistente'];

        $inserted = 0;
        foreach ($names as $name) {
            $exists = $this->db->table('employee_positions')->where('name', $name)->get()->getRow();
            if ($exists) {
                continue;
            }

            $this->db->table('employee_positions')->insert([
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        echo "EmployeePositionsSeeder: {$inserted} position(s) inserted.\n";
    }
}
