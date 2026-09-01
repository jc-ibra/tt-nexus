<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Defaults for the live GLPI overview (Resumen) inside HelpdeskSupervisor.
 * Connection stays reused from Provisioning; these knobs only scope what the
 * summary counts so SuperAdmin can tune entity/status/tops without code changes.
 */
class AddOverviewSettingsDefaults extends Migration
{
    public function up(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [
            // all = every entity; specific = filter by overview_entities_id (+ recursive)
            ['key' => 'overview_entities_mode',       'value' => 'all',       'updated_at' => $now],
            ['key' => 'overview_entities_id',         'value' => '0',         'updated_at' => $now],
            ['key' => 'overview_entities_recursive',  'value' => '1',         'updated_at' => $now],
            // GLPI status ids counted as "open backlog" (comma-separated)
            ['key' => 'overview_open_statuses',       'value' => '1,2,3,4',   'updated_at' => $now],
            // Ticket types: 1=Incidencia, 2=Requerimiento (empty = both)
            ['key' => 'overview_ticket_types',        'value' => '1,2',       'updated_at' => $now],
            // Optional root ITIL category ids; empty = all categories
            ['key' => 'overview_category_roots',      'value' => '',         'updated_at' => $now],
            ['key' => 'overview_top_n_categories',    'value' => '10',        'updated_at' => $now],
            ['key' => 'overview_top_n_sources',       'value' => '10',        'updated_at' => $now],
            ['key' => 'overview_critical_days',       'value' => '30',        'updated_at' => $now],
            ['key' => 'overview_cache_ttl',           'value' => '120',       'updated_at' => $now],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->table('helpdesk_supervisor_settings')
                ->where('key', $row['key'])
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('helpdesk_supervisor_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'overview_entities_mode',
            'overview_entities_id',
            'overview_entities_recursive',
            'overview_open_statuses',
            'overview_ticket_types',
            'overview_category_roots',
            'overview_top_n_categories',
            'overview_top_n_sources',
            'overview_critical_days',
            'overview_cache_ttl',
        ];
        $this->db->table('helpdesk_supervisor_settings')->whereIn('key', $keys)->delete();
    }
}
