<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit trail for attendance rows the supervisor registers or corrects by
 * hand (a forgotten check-in/out): who did it, when and why.
 */
class AddManualColumnsToAttendanceLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('servicedesk_attendance_logs', [
            'manual_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'permit_id'],
            'manual_at'     => ['type' => 'DATETIME', 'null' => true, 'after' => 'manual_by'],
            'manual_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'manual_at'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('servicedesk_attendance_logs', ['manual_by', 'manual_at', 'manual_reason']);
    }
}
