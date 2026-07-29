<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dispatch agents. Which Nexus users participate as agents of the dispatcher,
 * and which of them are dispatchers (may assign/reassign to others). This is
 * additive to the normal role/module access control — module access lets a
 * user in, being a registered agent lets them own conversations.
 */
class CreateMailDispatchAgentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'is_dispatcher' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'core_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('maildispatch_agents');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_agents', true);
    }
}
