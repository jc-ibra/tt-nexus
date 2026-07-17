<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default key/value rows for the daily Backlog Report into
 * servicedesk_settings. Idempotent: only inserts keys that are still missing,
 * so re-running never clobbers an admin's saved values.
 *
 * The report reads the GLPI open-ticket backlog, mails an HTML summary + xlsx
 * dump to a configured audience at a configured cut-off hour, and splits the
 * ticket base into Administración / Operaciones by ITIL category (see
 * servicedesk_backlog_areas). "Sin IDC" is derived from a plugin (Additional
 * Fields) column the admin picks live — empty column => the ticket has no IDC.
 */
class AddBacklogReportSettingsDefaults extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        // Master switch + scheduling.
        'backlog_enabled'        => '0',
        'backlog_send_hour'      => '08:00', // HH:MM in the app timezone
        'backlog_weekends'       => '1',     // send on Sat/Sun too
        'backlog_last_sent_date' => '',      // YYYY-MM-DD, set by the worker (guard)
        // Sender identity (uses the global SMTP transport, own From).
        'backlog_from_name'      => 'Mesa de Ayuda',
        'backlog_from_email'     => '',
        // Audience: comma/newline separated addresses.
        'backlog_to'             => '',
        'backlog_cc'             => '',
        // Copy.
        'backlog_subject_prefix' => 'Reporte Diario de Backlog',
        'backlog_org_label'      => 'Mesa de Ayuda',
        // Thresholds.
        'backlog_critical_days'  => '30',
        // "Sin IDC" source: a plugin container + one of its fields (empty = no IDC).
        'backlog_idc_container_id' => '0',
        'backlog_idc_field'        => '',
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
