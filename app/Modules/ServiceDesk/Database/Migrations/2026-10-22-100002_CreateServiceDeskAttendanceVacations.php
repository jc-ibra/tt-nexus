<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vacation periods registered by the attendance supervisor. Weekdays inside a
 * period are not counted as absences and show as "Vacaciones" on the board.
 */
class CreateServiceDeskAttendanceVacations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
            'notes'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'start_date', 'end_date']);
        $this->forge->addKey('start_date');
        $this->forge->createTable('servicedesk_attendance_vacations');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_attendance_vacations', true);
    }
}
