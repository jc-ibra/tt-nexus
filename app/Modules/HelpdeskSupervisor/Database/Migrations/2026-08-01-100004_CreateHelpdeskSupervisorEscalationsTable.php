<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Manual log of validated escalations (KPI 5). The supervisor records each
 * substantiated escalation/complaint against an agent for a measurement month.
 */
class CreateHelpdeskSupervisorEscalationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'glpi_ticket_id'        => ['type' => 'INT', 'unsigned' => true],
            'glpi_user_id'          => ['type' => 'INT', 'unsigned' => true],
            'nexus_user_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'agent_name'            => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'escalation_date'       => ['type' => 'DATE'],
            'reason'                => ['type' => 'TEXT'],
            'reported_by'           => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'validated_by_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'period_year'           => ['type' => 'SMALLINT', 'unsigned' => true],
            'period_month'          => ['type' => 'TINYINT', 'unsigned' => true],
            'is_valid'              => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['glpi_user_id', 'period_year', 'period_month']);
        $this->forge->addKey('glpi_ticket_id');
        $this->forge->createTable('helpdesk_supervisor_escalations');
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_escalations', true);
    }
}
