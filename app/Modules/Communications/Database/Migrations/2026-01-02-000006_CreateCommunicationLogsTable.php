<?php

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCommunicationLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'communication_id' => ['type' => 'INT', 'unsigned' => true],
            'recipient_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'email'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'           => ['type' => 'ENUM', 'constraint' => ['queued', 'sent', 'failed', 'bounced'], 'default' => 'queued'],
            'error_message'    => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'sent_at'          => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'       => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('communication_id');
        $this->forge->addForeignKey('communication_id', 'communications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('communication_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('communication_logs', true);
    }
}
