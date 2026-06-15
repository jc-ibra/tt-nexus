<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserRolesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'role_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'role_id']);
        $this->forge->addForeignKey('user_id', 'core_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'core_roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('core_user_roles');
    }

    public function down(): void
    {
        $this->forge->dropTable('core_user_roles', true);
    }
}
