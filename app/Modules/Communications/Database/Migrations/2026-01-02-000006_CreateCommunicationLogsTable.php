<?php

declare(strict_types=1);

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
            'tracking_token'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'default' => null],
            'opened_at'        => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'       => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('communication_id');
        $this->forge->addKey('tracking_token');
        $this->forge->addForeignKey('communication_id', 'comms_communications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'comms_recipients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('comms_communication_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('comms_communication_logs', true);
    }
}
