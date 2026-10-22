<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The supervisor's excused periods now carry a type: vacation or justified
 * absence. Both exempt the days from the absence count.
 */
class AddTypeToAttendanceVacations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('servicedesk_attendance_vacations', [
            // vacation | justified
            'type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'vacation', 'after' => 'user_id'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('servicedesk_attendance_vacations', 'type');
    }
}
