<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePermissionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'module_id' => ['type' => 'INT', 'unsigned' => true],
            'key'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['module_id', 'key']);
        $this->forge->addForeignKey('module_id', 'core_modules', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('core_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('core_permissions', true);
    }
}
