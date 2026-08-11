<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the "Estado" and "Municipio" source settings for the backlog report.
 * Same shape as the IDC/Regional pairs: the admin picks a plugin (Additional
 * Fields) container + field live, and the value lands as its own column of the
 * xlsx attachment. Unlike Regional, these two are export-only: they feed no KPI
 * and no section of the HTML email.
 *
 * Idempotent: only inserts keys that are still missing.
 */
class AddBacklogGeoSettings extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        'backlog_estado_container_id'    => '0',
        'backlog_estado_field'           => '',
        'backlog_municipio_container_id' => '0',
        'backlog_municipio_field'        => '',
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
