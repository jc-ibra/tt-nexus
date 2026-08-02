<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Snapshot of the data that fed each KPI, for traceability and drill-down. */
class CreateKpiSnapshotsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'evaluation_id'           => ['type' => 'INT', 'unsigned' => true],
            'kpi_number'              => ['type' => 'TINYINT', 'unsigned' => true],
            'total_tickets_evaluated' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'tickets_meeting_criteria' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'calculated_value'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'threshold_met'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'detail_json'             => ['type' => 'TEXT', 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['evaluation_id', 'kpi_number']);
        $this->forge->addForeignKey('evaluation_id', 'agent_kpis_monthly_evaluations', 'id', '', 'CASCADE');
        $this->forge->createTable('agent_kpis_kpi_snapshots');
    }

    public function down(): void
    {
        $this->forge->dropTable('agent_kpis_kpi_snapshots', true);
    }
}
