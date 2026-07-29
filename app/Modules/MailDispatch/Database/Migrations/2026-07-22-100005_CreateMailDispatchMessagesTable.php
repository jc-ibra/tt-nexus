<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Individual messages of a conversation. Idempotency key is graph_id (unique):
 * re-running the sync never duplicates. internet_message_id / in_reply_to /
 * references support the threading fallback when Graph's conversationId breaks.
 */
class CreateMailDispatchMessagesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'conversation_id'     => ['type' => 'INT', 'unsigned' => true],
            // Graph message id — unique, the idempotency key.
            'graph_id'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'internet_message_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'in_reply_to'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'references_header'   => ['type' => 'TEXT', 'null' => true],
            'direction'           => ['type' => 'ENUM', 'constraint' => ['in', 'out'], 'default' => 'in'],
            'from_name'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'from_email'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'to_recipients'       => ['type' => 'TEXT', 'null' => true],
            'subject'             => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'body_preview'        => ['type' => 'TEXT', 'null' => true],
            'body'                => ['type' => 'MEDIUMTEXT', 'null' => true],
            'body_is_html'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'has_attachments'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'attachment_names'    => ['type' => 'TEXT', 'null' => true],
            'received_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('graph_id');
        $this->forge->addKey('conversation_id');
        $this->forge->addKey('internet_message_id');
        $this->forge->addForeignKey('conversation_id', 'maildispatch_conversations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('maildispatch_messages');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_messages', true);
    }
}
