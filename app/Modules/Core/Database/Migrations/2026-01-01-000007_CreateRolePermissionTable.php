<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolePermissionTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_id'       => ['type' => 'INT', 'unsigned' => true],
            'permission_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['role_id', 'permission_id']);
        $this->forge->addForeignKey('role_id',       'core_roles',       'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'core_permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('core_role_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('core_role_permissions', true);
    }
}
