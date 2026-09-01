<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds top-N requesters knob for the GLPI overview. */
class AddOverviewTopRequestersSetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('helpdesk_supervisor_settings')
            ->where('key', 'overview_top_n_requesters')
            ->countAllResults();
        if ($exists === 0) {
            $this->db->table('helpdesk_supervisor_settings')->insert([
                'key'        => 'overview_top_n_requesters',
                'value'      => '15',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('helpdesk_supervisor_settings')->where('key', 'overview_top_n_requesters')->delete();
    }
}
