<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reply templates (phase 3). Operational CRUD catalog (lives in the module,
 * not Core admin) used when replying to a thread from Nexus. Body supports
 * simple variables like {{requester_name}}.
 */
class CreateMailDispatchTemplatesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'subject'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'body'       => ['type' => 'MEDIUMTEXT', 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('created_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maildispatch_templates');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_templates', true);
    }
}
