<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvisioningExternalAccountsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'   => ['type' => 'INT', 'unsigned' => true],
            'system_id'     => ['type' => 'INT', 'unsigned' => true],
            'external_id'   => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'default' => null],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'last_message'  => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'last_sync_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['employee_id', 'system_id']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('employee_id', 'employees_employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('system_id',   'provisioning_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('provisioning_external_accounts');
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_external_accounts', true);
    }
}
