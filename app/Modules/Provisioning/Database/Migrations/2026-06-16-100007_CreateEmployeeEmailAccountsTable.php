<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeEmailAccountsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'    => ['type' => 'INT', 'unsigned' => true],
            'type'           => ['type' => 'ENUM', 'constraint' => ['mailcow', 'microsoft', 'none']],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'ms_license_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'is_primary'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'notes'          => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'created_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('type');
        $this->forge->addForeignKey('employee_id', 'employees_employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ms_license_id', 'provisioning_ms_licenses', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('employee_email_accounts');

        // Migrate existing employees that have a Mailcow mailbox linked.
        $now = date('Y-m-d H:i:s');
        $employees = $this->db->table('employees_employees')
            ->where('has_mailbox', 1)
            ->where('email IS NOT NULL', null, false)
            ->where('email !=', '')
            ->get()
            ->getResultArray();

        foreach ($employees as $emp) {
            $this->db->table('employee_email_accounts')->insert([
                'employee_id' => $emp['id'],
                'type'        => 'mailcow',
                'email'       => $emp['email'],
                'is_primary'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_email_accounts', true);
    }
}
