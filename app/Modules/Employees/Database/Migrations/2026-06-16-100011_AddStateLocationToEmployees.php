<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStateLocationToEmployees extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('employees_employees', [
            'state_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'area_id',
            ],
            'location_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'state_id',
            ],
        ]);

        $this->db->query('ALTER TABLE `employees_employees` ADD CONSTRAINT `emp_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `employees_states` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `employees_employees` ADD CONSTRAINT `emp_location_id_fk` FOREIGN KEY (`location_id`) REFERENCES `employees_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `employees_employees` DROP FOREIGN KEY `emp_state_id_fk`');
        $this->db->query('ALTER TABLE `employees_employees` DROP FOREIGN KEY `emp_location_id_fk`');

        $this->forge->dropColumn('employees_employees', ['state_id', 'location_id']);
    }
}
