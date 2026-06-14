<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeePositionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('status');
        $this->forge->createTable('employee_positions');
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_positions', true);
    }
}
