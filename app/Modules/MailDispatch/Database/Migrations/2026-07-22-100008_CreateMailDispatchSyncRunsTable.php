<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Log of individual sync runs, shown in the admin status panel. Every run of
 * maildispatch:sync-mailbox appends a row with counters and outcome so the
 * SuperAdmin can see recent activity and errors without touching the server.
 */
class CreateMailDispatchSyncRunsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'mailbox_address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['ok', 'error'], 'default' => 'ok'],
            'trigger'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'processed'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'errors'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'message'         => ['type' => 'TEXT', 'null' => true],
            'duration_ms'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('maildispatch_sync_runs');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_sync_runs', true);
    }
}
