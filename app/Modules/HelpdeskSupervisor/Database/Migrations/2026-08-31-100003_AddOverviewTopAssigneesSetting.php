<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds top-N assignees knob for the GLPI overview. */
class AddOverviewTopAssigneesSetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('helpdesk_supervisor_settings')
            ->where('key', 'overview_top_n_assignees')
            ->countAllResults();
        if ($exists === 0) {
            $this->db->table('helpdesk_supervisor_settings')->insert([
                'key'        => 'overview_top_n_assignees',
                'value'      => '15',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('helpdesk_supervisor_settings')->where('key', 'overview_top_n_assignees')->delete();
    }
}
