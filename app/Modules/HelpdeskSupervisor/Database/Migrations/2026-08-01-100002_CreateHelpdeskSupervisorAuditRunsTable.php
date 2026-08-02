<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per audit execution. Runs are never deleted: re-auditing a period
 * creates a new run and keeps the previous ones for history.
 */
class CreateHelpdeskSupervisorAuditRunsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'period_start'          => ['type' => 'DATE'],
            'period_end'            => ['type' => 'DATE'],
            // NULL = all mapped agents; a value = a single agent run.
            'agent_glpi_user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'total_tickets_audited' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_deviations_found' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_agents_audited'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'status'                => ['type' => 'ENUM', 'constraint' => ['running', 'completed', 'failed'], 'default' => 'running'],
            'error_message'         => ['type' => 'TEXT', 'null' => true],
            'run_by_user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'started_at'            => ['type' => 'DATETIME', 'null' => true],
            'completed_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['period_start', 'period_end']);
        $this->forge->addKey('status');
        $this->forge->createTable('helpdesk_supervisor_audit_runs');
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_audit_runs', true);
    }
}
