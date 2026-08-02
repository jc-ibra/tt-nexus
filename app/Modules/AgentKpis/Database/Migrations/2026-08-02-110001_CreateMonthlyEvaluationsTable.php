<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Consolidated monthly evaluation per agent: the 5 KPIs (value + status), the
 * quantitative level/score, the qualitative score, the KPI-5 block rule and the
 * final score/status.
 */
class CreateMonthlyEvaluationsTable extends Migration
{
    public function up(): void
    {
        $status = ['cumple', 'parcial', 'no_cumple'];

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nexus_user_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'glpi_user_id'          => ['type' => 'INT', 'unsigned' => true],
            'agent_name'            => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'period_year'           => ['type' => 'SMALLINT', 'unsigned' => true],
            'period_month'          => ['type' => 'TINYINT', 'unsigned' => true],
            'audit_run_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'total_tickets'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'kpi1_value'            => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'kpi1_status'           => ['type' => 'ENUM', 'constraint' => $status, 'null' => true],
            'kpi2_value'            => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'kpi2_status'           => ['type' => 'ENUM', 'constraint' => $status, 'null' => true],
            'kpi3_value'            => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'kpi3_status'           => ['type' => 'ENUM', 'constraint' => $status, 'null' => true],
            'kpi4_value'            => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'kpi4_status'           => ['type' => 'ENUM', 'constraint' => $status, 'null' => true],
            'kpi5_escalations_count' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'kpi5_status'           => ['type' => 'ENUM', 'constraint' => $status, 'null' => true],
            'kpis_met_count'        => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'quantitative_level'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'quantitative_score'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'qualitative_score_raw' => ['type' => 'DECIMAL', 'constraint' => '3,2', 'null' => true],
            'qualitative_score'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'is_blocked'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'final_score'           => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'final_status'          => ['type' => 'ENUM', 'constraint' => ['blocked', 'evaluated', 'pending_qualitative', 'draft'], 'default' => 'draft'],
            'evaluated_by_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'evaluated_at'          => ['type' => 'DATETIME', 'null' => true],
            'agent_comments'        => ['type' => 'TEXT', 'null' => true],
            'supervisor_notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['nexus_user_id', 'period_year', 'period_month']);
        $this->forge->addKey(['glpi_user_id', 'period_year', 'period_month']);
        $this->forge->createTable('agent_kpis_monthly_evaluations');
    }

    public function down(): void
    {
        $this->forge->dropTable('agent_kpis_monthly_evaluations', true);
    }
}
