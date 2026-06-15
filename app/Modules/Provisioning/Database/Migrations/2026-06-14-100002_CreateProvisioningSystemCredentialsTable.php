<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvisioningSystemCredentialsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'system_id'       => ['type' => 'INT', 'unsigned' => true],
            'credential_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'value_encrypted' => ['type' => 'TEXT'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'rotated_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['system_id', 'credential_name']);
        $this->forge->addForeignKey('system_id', 'provisioning_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('provisioning_system_credentials');
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_system_credentials', true);
    }
}
