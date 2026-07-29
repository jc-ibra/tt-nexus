<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One active conversation state per technician (unique telegram_chat_id).
 *
 * Drives the state machine (spec §7): which step of a flow the technician is on,
 * the accumulated context (partial texts, pending photos, chosen ticket), and an
 * inactivity timeout after which the flow resets to idle.
 */
class CreateTechBotConversationStatesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'telegram_chat_id'  => ['type' => 'BIGINT'],
            'state'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'context'           => ['type' => 'JSON', 'null' => true, 'default' => null],
            'current_ticket_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'expires_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('telegram_chat_id');
        $this->forge->addForeignKey('telegram_chat_id', 'techbot_telegram_links', 'telegram_chat_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('techbot_conversation_states');
    }

    public function down(): void
    {
        $this->forge->dropTable('techbot_conversation_states', true);
    }
}
