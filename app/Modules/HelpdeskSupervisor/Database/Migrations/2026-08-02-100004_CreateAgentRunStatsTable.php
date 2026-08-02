<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-agent, per-run ticket counts. The run row only keeps global totals, but
 * the KPI math (Fase 3) needs each agent's total tickets (denominator for
 * KPI 1-3) and open tickets (denominator for KPI 4). Populated by
 * AuditRunnerService.
 */
class CreateAgentRunStatsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'audit_run_id'  => ['type' => 'INT', 'unsigned' => true],
            'glpi_user_id'  => ['type' => 'INT', 'unsigned' => true],
            'nexus_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'agent_name'    => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'total_tickets' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'open_tickets'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['audit_run_id', 'glpi_user_id']);
        $this->forge->addForeignKey('audit_run_id', 'helpdesk_supervisor_audit_runs', 'id', '', 'CASCADE');
        $this->forge->createTable('helpdesk_supervisor_agent_run_stats');
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_agent_run_stats', true);
    }
}
