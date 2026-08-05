<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Attachments of a message. Files live on disk under
 * WRITEPATH/maildispatch/attachments/{message_id}/; this table holds the
 * metadata and the relative storage path. content_id + is_inline support inline
 * images referenced by cid: in the HTML body. direction mirrors the parent
 * message (inbound customer file vs. outbound agent reply file).
 */
class CreateMailDispatchAttachmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'message_id'      => ['type' => 'INT', 'unsigned' => true],
            'conversation_id' => ['type' => 'INT', 'unsigned' => true],
            'filename'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'size_bytes'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            // Path relative to WRITEPATH.
            'storage_path'    => ['type' => 'VARCHAR', 'constraint' => 500],
            // RFC content-id (angle brackets stripped) for inline parts.
            'content_id'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_inline'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'direction'       => ['type' => 'ENUM', 'constraint' => ['in', 'out'], 'default' => 'in'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('message_id');
        $this->forge->addKey('conversation_id');
        $this->forge->addForeignKey('message_id', 'maildispatch_messages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('conversation_id', 'maildispatch_conversations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('maildispatch_attachments');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_attachments', true);
    }
}
