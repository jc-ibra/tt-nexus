<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvisioningLogTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'system_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'operation'         => ['type' => 'VARCHAR', 'constraint' => 40],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'message'           => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'payload_summary'   => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'external_id'       => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'default' => null],
            'error_code'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'default' => null],
            'executor_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'ip_address'        => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true, 'default' => null],
            'created_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('system_id');
        $this->forge->addKey('status');
        $this->forge->addKey(['operation', 'status']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('system_id', 'provisioning_systems', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('provisioning_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_log', true);
    }
}
