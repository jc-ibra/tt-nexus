<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Real-time deviations from GLPI webhooks (create/update). Separate from
 * period audit_runs so monthly KPIs / rankings stay immutable.
 */
class CreateLiveDeviationsTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'glpi_ticket_id'   => ['type' => 'INT', 'unsigned' => true],
            'glpi_ticket_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'glpi_user_id'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'nexus_user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'agent_name'       => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'rule_key'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'rule_name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'severity'         => ['type' => 'ENUM', 'constraint' => ['critical', 'warning', 'info'], 'default' => 'warning'],
            'field_key'        => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'field_affected'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'expected_value'   => ['type' => 'TEXT', 'null' => true],
            'actual_value'     => ['type' => 'TEXT', 'null' => true],
            'detail'           => ['type' => 'TEXT', 'null' => true],
            'manual_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'kpi_mapping'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['open', 'resolved'], 'default' => 'open'],
            'event_source'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'update'],
            'first_seen_at'    => ['type' => 'DATETIME', 'null' => true],
            'last_seen_at'     => ['type' => 'DATETIME', 'null' => true],
            'resolved_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['glpi_ticket_id', 'rule_key', 'field_key'], 'uniq_hs_live_dev');
        $this->forge->addKey(['status', 'last_seen_at'], false, false, 'idx_hs_live_status_seen');
        $this->forge->addKey('glpi_user_id');
        $this->forge->addKey('rule_key');
        $this->forge->createTable('helpdesk_supervisor_live_deviations', true);

        $this->forge->addField([
            'glpi_ticket_id'    => ['type' => 'INT', 'unsigned' => true],
            'last_event'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => ''],
            'last_processed_at' => ['type' => 'DATETIME', 'null' => true],
            'last_open_count'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('glpi_ticket_id');
        $this->forge->createTable('helpdesk_supervisor_live_ticket_state', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_live_ticket_state', true);
        $this->forge->dropTable('helpdesk_supervisor_live_deviations', true);
    }
}
