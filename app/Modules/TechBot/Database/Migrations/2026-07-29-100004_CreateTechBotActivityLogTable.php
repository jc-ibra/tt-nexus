<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit trail of every action a technician performs through the bot: which
 * template, which GLPI followup/solution resulted, the status transition, the
 * payload sent to GLPI, and whether Claude was used. Never stores secrets.
 */
class CreateTechBotActivityLogTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'telegram_chat_id'   => ['type' => 'BIGINT'],
            'employee_id'        => ['type' => 'INT', 'unsigned' => true],
            'glpi_ticket_id'     => ['type' => 'INT', 'unsigned' => true],
            'action'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'template_key'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            'glpi_followup_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'glpi_status_before' => ['type' => 'INT', 'null' => true, 'default' => null],
            'glpi_status_after'  => ['type' => 'INT', 'null' => true, 'default' => null],
            'payload'            => ['type' => 'JSON', 'null' => true, 'default' => null],
            'ai_used'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'ai_tokens_used'     => ['type' => 'INT', 'null' => true, 'default' => null],
            'result'             => ['type' => 'ENUM', 'constraint' => ['success', 'error']],
            'error_message'      => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'created_at'         => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['employee_id', 'created_at']);
        $this->forge->addKey('glpi_ticket_id');
        $this->forge->createTable('techbot_activity_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('techbot_activity_log', true);
    }
}
