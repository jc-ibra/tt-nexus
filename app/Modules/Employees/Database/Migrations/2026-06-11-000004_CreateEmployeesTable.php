<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_number'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 180],
            'lastname'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 191],
            'email_secondary'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'has_mailbox'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'photo'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'telephone'         => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'default' => null],
            'cellphone'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            'ext'               => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            'position_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'department_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'area_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'parent_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'date_entry'        => ['type' => 'DATE', 'null' => true, 'default' => null],
            'date_discharge'    => ['type' => 'DATE', 'null' => true, 'default' => null],
            'hide_emails'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'show_in_directory' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('employee_number');
        $this->forge->addKey('active');
        $this->forge->addKey('position_id');
        $this->forge->addKey('department_id');
        $this->forge->addKey('area_id');
        $this->forge->addKey('parent_id');

        // CI4 signature: addForeignKey($fieldName, $tableName, $tableField, $onUpdate, $onDelete)
        $this->forge->addForeignKey('position_id', 'employee_positions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('department_id', 'employee_departments', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('area_id', 'employee_areas', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('employees');

        // Self-referential FK added after the table exists.
        $this->db->query('ALTER TABLE `employees` ADD CONSTRAINT `employees_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $this->forge->dropTable('employees', true);
    }
}
