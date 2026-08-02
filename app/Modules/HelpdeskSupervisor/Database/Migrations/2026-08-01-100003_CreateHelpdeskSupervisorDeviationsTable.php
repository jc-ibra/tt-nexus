<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Each deviation found by a rule, snapshotted so the dashboard never has to hit
 * GLPI again to render history. Tickets/agent names are stored as they were at
 * audit time.
 */
class CreateHelpdeskSupervisorDeviationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'audit_run_id'     => ['type' => 'INT', 'unsigned' => true],
            'glpi_ticket_id'   => ['type' => 'INT', 'unsigned' => true],
            'glpi_ticket_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'glpi_user_id'     => ['type' => 'INT', 'unsigned' => true],
            // Nexus user mapped by glpi_user_id (NULL when unmapped).
            'nexus_user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'agent_name'       => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'rule_key'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'rule_name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'severity'         => ['type' => 'ENUM', 'constraint' => ['critical', 'warning', 'info'], 'default' => 'warning'],
            'field_affected'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'expected_value'   => ['type' => 'TEXT', 'null' => true],
            'actual_value'     => ['type' => 'TEXT', 'null' => true],
            'detail'           => ['type' => 'TEXT', 'null' => true],
            'manual_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            // Which KPI this deviation feeds (KPI-1..KPI-4), NULL if none.
            'kpi_mapping'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('audit_run_id');
        $this->forge->addKey('glpi_user_id');
        $this->forge->addKey('rule_key');
        $this->forge->addKey('glpi_ticket_id');
        $this->forge->addKey('kpi_mapping');
        $this->forge->addForeignKey('audit_run_id', 'helpdesk_supervisor_audit_runs', 'id', '', 'CASCADE');
        $this->forge->createTable('helpdesk_supervisor_deviations');
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_deviations', true);
    }
}
