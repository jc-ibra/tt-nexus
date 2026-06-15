<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoleModuleTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_id'   => ['type' => 'INT', 'unsigned' => true],
            'module_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['role_id', 'module_id']);
        $this->forge->addForeignKey('role_id',   'core_roles',   'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('module_id', 'core_modules', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('core_role_modules');
    }

    public function down(): void
    {
        $this->forge->dropTable('core_role_modules', true);
    }
}
