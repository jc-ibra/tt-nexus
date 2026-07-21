<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the setting that lists which plugin containers' additional fields the
 * public landing form requests. The SuperAdmin picks the containers (like the
 * "Tipo de ticket" selector in /servicedesk/creator); the /soporte form then
 * renders those containers' fields. Stored as a CSV of container ids.
 *
 * Idempotent: only inserts the key when still missing.
 */
class AddLandingContainerSettings extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        'landing_container_ids' => '',
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
