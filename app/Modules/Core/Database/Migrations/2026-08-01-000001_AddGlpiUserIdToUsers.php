<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Maps a Nexus user to its GLPI user id, so modules that audit GLPI activity
 * (HelpdeskSupervisor, AgentKpis) can attribute tickets/deviations to the right
 * Nexus agent.
 *
 * Nullable and NOT unique on purpose: most Nexus users are never mapped, and
 * only the MAC agents that are actually measured carry a value.
 */
class AddGlpiUserIdToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('core_users', [
            'glpi_user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
                'after'    => 'status',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('core_users', 'glpi_user_id');
    }
}
