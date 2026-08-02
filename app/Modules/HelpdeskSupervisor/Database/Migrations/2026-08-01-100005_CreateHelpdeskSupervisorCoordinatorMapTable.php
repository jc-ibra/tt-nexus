<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * State -> operational coordinator map (Manual Parte 3.5). Feeds the
 * CoordinatorAssignmentRule: the ticket's assigned user must match the
 * coordinator expected for the state captured in the Clientes Externos tab.
 *
 * The coordinator's GLPI user id is nullable: it is resolved from the
 * coordinator name against glpi_users (or edited from the UI) after seeding,
 * since the seeder only knows names from the manual.
 */
class CreateHelpdeskSupervisorCoordinatorMapTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                       => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'state_name'               => ['type' => 'VARCHAR', 'constraint' => 100],
            'coordinator_glpi_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'coordinator_name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'zone'                     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'created_at'               => ['type' => 'DATETIME', 'null' => true],
            'updated_at'               => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('state_name');
        $this->forge->createTable('helpdesk_supervisor_coordinator_map');
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_coordinator_map', true);
    }
}
