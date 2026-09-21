<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only audit trail for the employee record: one row per changed field
 * per save, grouped by `event_id`. Populated exclusively via EmployeeModel's
 * insert/update/delete callbacks (see EmployeeAuditService), so every writer
 * — including Provisioning's AccessOrchestrator, which updates the employee
 * row directly on baja/reactivación — is captured without being touched.
 */
class CreateEmployeesAuditLogTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'    => ['type' => 'INT', 'unsigned' => true],
            'event_id'       => ['type' => 'CHAR', 'constraint' => 36],
            'action'         => ['type' => 'VARCHAR', 'constraint' => 30],
            'field'          => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'default' => null],
            'old_value'      => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'new_value'      => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'actor_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'actor_name'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'default' => null],
            'source'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'web'],
            'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true, 'default' => null],
            'created_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['employee_id', 'created_at']);
        $this->forge->addKey('event_id');
        $this->forge->addKey('action');
        $this->forge->addKey('field');
        $this->forge->addKey('actor_user_id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('employee_id', 'employees_employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'core_users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('employees_audit_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('employees_audit_log', true);
    }
}
