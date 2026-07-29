<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Conversations — the unit of work. One row per Graph conversation (thread).
 * State machine: nueva -> asignada -> en_atencion -> respondida <-> esperando_agente -> cerrada.
 * A closed conversation that receives a new inbound message reopens into
 * esperando_agente keeping its prior assignment.
 */
class CreateMailDispatchConversationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // Graph conversationId — unique thread key.
            'conversation_id'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'mailbox_address'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'subject'          => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'requester_name'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'requester_email'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'           => [
                'type'       => 'ENUM',
                'constraint' => ['nueva', 'asignada', 'en_atencion', 'respondida', 'esperando_agente', 'cerrada'],
                'default'    => 'nueva',
            ],
            'agent_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'disposition_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'glpi_folio'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'close_comment'    => ['type' => 'TEXT', 'null' => true],
            'message_count'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            // Key timestamps for SLA/metrics.
            'received_at'      => ['type' => 'DATETIME', 'null' => true],
            'assigned_at'      => ['type' => 'DATETIME', 'null' => true],
            'first_response_at' => ['type' => 'DATETIME', 'null' => true],
            'last_activity_at' => ['type' => 'DATETIME', 'null' => true],
            'closed_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('conversation_id');
        $this->forge->addKey('status');
        $this->forge->addKey('agent_id');
        $this->forge->addKey('last_activity_at');
        $this->forge->addForeignKey('agent_id', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('disposition_id', 'maildispatch_dispositions', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maildispatch_conversations');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_conversations', true);
    }
}
