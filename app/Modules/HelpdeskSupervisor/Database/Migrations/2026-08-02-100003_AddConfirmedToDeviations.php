<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the supervisor's "procede" confirmation to deviations. Only confirmed
 * deviations are surfaced to the agent's self-view (in ServiceDesk). Auto-detected
 * deviations stay is_confirmed = 0 until the supervisor validates them.
 */
class AddConfirmedToDeviations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('helpdesk_supervisor_deviations', [
            'is_confirmed'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'kpi_mapping'],
            'confirmed_at'         => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_confirmed'],
            'confirmed_by_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'confirmed_at'],
        ]);
        $this->db->query('CREATE INDEX idx_hs_dev_confirmed ON helpdesk_supervisor_deviations (glpi_user_id, is_confirmed)');
    }

    public function down(): void
    {
        $this->forge->dropColumn('helpdesk_supervisor_deviations', ['is_confirmed', 'confirmed_at', 'confirmed_by_user_id']);
    }
}
