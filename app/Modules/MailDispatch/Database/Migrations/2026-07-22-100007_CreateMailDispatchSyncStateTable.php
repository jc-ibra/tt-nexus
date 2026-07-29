<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Persistent sync state per mailbox + folder. Holds the current Graph delta
 * link/token so each run only pulls changes since the last run. We track the
 * Inbox (incoming) and Sent Items (agent replies from Outlook) separately, each
 * with its own delta link — hence one row per (mailbox, folder). Unique on that
 * pair; the design already supports multiple mailboxes even though the UI
 * manages one.
 */
class CreateMailDispatchSyncStateTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'mailbox_address' => ['type' => 'VARCHAR', 'constraint' => 255],
            // Well-known folder name: 'inbox' or 'sentitems'.
            'folder'          => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'inbox'],
            'delta_link'      => ['type' => 'TEXT', 'null' => true],
            'last_run_at'     => ['type' => 'DATETIME', 'null' => true],
            'last_result'     => ['type' => 'ENUM', 'constraint' => ['ok', 'error', 'never'], 'default' => 'never'],
            'last_message'    => ['type' => 'TEXT', 'null' => true],
            'processed_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'error_count'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['mailbox_address', 'folder']);
        $this->forge->createTable('maildispatch_sync_state');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_sync_state', true);
    }
}
