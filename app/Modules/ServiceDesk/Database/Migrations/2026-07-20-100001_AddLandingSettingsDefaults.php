<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default key/value rows for the PUBLIC self-service landing into
 * servicedesk_settings. The landing is a standalone page (route /soporte),
 * independent from the embeddable widget: it has its own enable toggle and site
 * key, collects the requester identity in a form, and lets the user pick the
 * ITIL category from the supported list.
 *
 * Idempotent: only inserts keys that are still missing, so re-running never
 * clobbers an admin's saved values. The ServiceDeskSettings accessors already
 * fall back to these defaults when a row is absent; this migration only makes
 * the rows visible in the admin form and keeps the settings table complete.
 */
class AddLandingSettingsDefaults extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        'landing_enabled'             => '0',
        'landing_site_key'            => '',
        'landing_title'               => '',
        'landing_intro'               => '',
        'landing_rate_limit_per_hour' => '10',
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
