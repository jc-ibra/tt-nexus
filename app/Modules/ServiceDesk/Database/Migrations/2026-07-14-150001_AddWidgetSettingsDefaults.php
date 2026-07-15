<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default key/value rows for the self-service widget into
 * servicedesk_settings. Idempotent: only inserts keys that are still missing,
 * so re-running never clobbers an admin's saved values.
 *
 * Note: the ServiceDeskSettings accessors already fall back to these defaults
 * when a row is absent; this migration only makes the rows visible in the admin
 * form and keeps the settings table complete.
 */
class AddWidgetSettingsDefaults extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        'widget_enabled'             => '0',
        'widget_site_key'            => '',
        'widget_allowed_origins'     => '',
        'widget_title'               => '',
        'widget_welcome'             => '',
        'widget_system_prompt'       => '',
        'widget_container_id'        => '0',
        'widget_field_equipo'        => '',
        'widget_field_modelo'        => '',
        'widget_field_serie'         => '',
        'widget_field_categoria'     => '',
        'widget_itil_category_id'    => '0',
        'widget_requester_user_id'   => '0',
        'widget_entities_id'         => '',
        'widget_rate_limit_per_hour' => '20',
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
