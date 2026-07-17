<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the "Regional" source settings for the backlog report. Like IDC, the
 * regional split comes from a plugin (Additional Fields) column the admin picks
 * live: the report groups the backlog by that field's value into the
 * "POR REGIONAL" table (tickets, avg. days open, sin IDC per regional).
 *
 * Idempotent: only inserts keys that are still missing.
 */
class AddBacklogRegionalSettings extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        'backlog_regional_container_id' => '0',
        'backlog_regional_field'        => '',
    ];

    public function up(): void
    {
        $table = $this->db->table('servicedesk_settings');
        $now   = date('Y-m-d H:i:s');

        foreach ($this->defaults as $key => $value) {
            $exists = $this->db->table('servicedesk_settings')
                ->where('key', $key)
                ->countAllResults();
            if ($exists === 0) {
                $table->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('servicedesk_settings')
            ->whereIn('key', array_keys($this->defaults))
            ->delete();
    }
}
