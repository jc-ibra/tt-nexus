<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default key/value rows for Attendance into servicedesk_settings.
 * Idempotent: only inserts keys still missing, so a re-run never clobbers an
 * admin's saved values.
 */
class AddAttendanceSettingsDefaults extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        'attendance_enabled'             => '1',
        // HH:MM in the app timezone; late = check_in_at later than this + tolerance.
        'attendance_expected_checkin'    => '09:00',
        'attendance_tolerance_minutes'   => '15',
        // Nexus user id of the single attendance supervisor. 0 = unset (SuperAdmin
        // can still approve/export while unset).
        'attendance_supervisor_user_id'  => '0',
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
