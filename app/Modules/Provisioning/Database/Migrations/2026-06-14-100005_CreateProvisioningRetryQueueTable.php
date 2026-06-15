<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvisioningRetryQueueTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'log_id'          => ['type' => 'INT', 'unsigned' => true],
            'employee_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'system_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'operation'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'payload'         => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'attempts'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'max_attempts'    => ['type' => 'INT', 'unsigned' => true, 'default' => 5],
            'next_attempt_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'last_error'      => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('next_attempt_at');
        $this->forge->addForeignKey('log_id', 'provisioning_log', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('system_id', 'provisioning_systems', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('provisioning_retry_queue');
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_retry_queue', true);
    }
}
