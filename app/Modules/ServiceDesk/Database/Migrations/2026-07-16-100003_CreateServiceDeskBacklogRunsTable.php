<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit trail of backlog report sends. One row per attempt (scheduled, manual
 * or test) so the admin can see what went out, to whom, and whether it failed.
 * Also backs the "already sent today" guard together with backlog_last_sent_date.
 */
class CreateServiceDeskBacklogRunsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // Business date of the cut-off (YYYY-MM-DD).
            'report_date' => ['type' => 'DATE', 'null' => true],
            // scheduled | manual | test
            'trigger'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'scheduled'],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ok'], // ok | failed
            'total_open'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'recipients'  => ['type' => 'TEXT', 'null' => true],   // to + cc, for the record
            'error'       => ['type' => 'TEXT', 'null' => true],
            'triggered_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('report_date');
        $this->forge->createTable('servicedesk_backlog_runs');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_backlog_runs', true);
    }
}
