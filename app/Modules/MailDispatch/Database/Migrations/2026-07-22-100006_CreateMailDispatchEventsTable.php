<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit log for a conversation: assignments/reassignments, state transitions,
 * closes, reopens, and internal agent notes. Every mutation records who did
 * what and when. type=note carries a free-text internal note (Nexus-only).
 */
class CreateMailDispatchEventsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'conversation_id' => ['type' => 'INT', 'unsigned' => true],
            // Who performed it (null = system, e.g. auto-transition on sync).
            'user_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'type'            => [
                'type'       => 'ENUM',
                'constraint' => ['assign', 'reassign', 'unassign', 'status', 'close', 'reopen', 'note'],
            ],
            'from_value'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'to_value'        => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'note'            => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('conversation_id');
        $this->forge->addKey('type');
        $this->forge->addForeignKey('conversation_id', 'maildispatch_conversations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maildispatch_events');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_events', true);
    }
}
