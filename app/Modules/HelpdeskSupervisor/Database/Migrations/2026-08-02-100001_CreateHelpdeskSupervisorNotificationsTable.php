<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per agent notification generated and/or sent (Fase 2). Holds the AI
 * draft, the supervisor's final body, the generated Excel path and delivery
 * status, plus token accounting for the IA cost follow-up.
 */
class CreateHelpdeskSupervisorNotificationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'audit_run_id'     => ['type' => 'INT', 'unsigned' => true],
            'glpi_user_id'     => ['type' => 'INT', 'unsigned' => true],
            'nexus_user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'agent_name'       => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'agent_email'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'period_start'     => ['type' => 'DATE'],
            'period_end'       => ['type' => 'DATE'],
            'total_deviations' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'ai_draft_body'    => ['type' => 'TEXT', 'null' => true],
            'final_body'       => ['type' => 'TEXT', 'null' => true],
            'excel_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['draft', 'ready', 'sent', 'failed'], 'default' => 'draft'],
            'sent_at'          => ['type' => 'DATETIME', 'null' => true],
            'sent_by_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'error_message'    => ['type' => 'TEXT', 'null' => true],
            'ai_tokens_input'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'ai_tokens_output' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('audit_run_id');
        $this->forge->addKey(['glpi_user_id', 'status']);
        $this->forge->addForeignKey('audit_run_id', 'helpdesk_supervisor_audit_runs', 'id', '', 'CASCADE');
        $this->forge->createTable('helpdesk_supervisor_notifications');
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_notifications', true);
    }
}
