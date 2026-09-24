<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Attendance / jornada laboral: agents check in/out each day and pick a
 * scheme (presencial / home_office / permiso). "Permiso" (home office on a
 * day that was scheduled presencial) needs retroactive supervisor approval,
 * tracked in a separate table so a log row never disappears while pending.
 *
 * Read by the whole ServiceDesk team (transparency: who is in, who covers
 * whom) and by the single attendance supervisor mirrored in HelpdeskSupervisor
 * (history, approvals, export).
 */
class CreateServiceDeskAttendanceTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'work_date'     => ['type' => 'DATE'],
            // presencial | home_office | permiso
            'scheme'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'check_in_at'   => ['type' => 'DATETIME', 'null' => true],
            'check_out_at'  => ['type' => 'DATETIME', 'null' => true],
            // Set only when scheme = permiso, links to its approval row.
            'permit_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'work_date']);
        $this->forge->addKey('work_date');
        $this->forge->createTable('servicedesk_attendance_logs');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'work_date'     => ['type' => 'DATE'],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            // pending | approved | rejected
            'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'],
            'reviewed_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_at'   => ['type' => 'DATETIME', 'null' => true],
            'review_notes'  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('status');
        $this->forge->addKey('work_date');
        $this->forge->createTable('servicedesk_attendance_permits');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_attendance_permits', true);
        $this->forge->dropTable('servicedesk_attendance_logs', true);
    }
}
