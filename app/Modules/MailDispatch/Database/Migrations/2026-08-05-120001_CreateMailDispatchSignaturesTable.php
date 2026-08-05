<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-agent email signature. Each user configures their own HTML signature,
 * which is appended to every reply they send from Nexus (above the quoted
 * history). Keyed by user id so it applies to any replier (agent or dispatcher),
 * independent of the agents table.
 */
class CreateMailDispatchSignaturesTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('maildispatch_signatures')) {
            return;
        }
        $this->forge->addField([
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'body_html'  => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('user_id', true); // primary
        $this->forge->addForeignKey('user_id', 'core_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('maildispatch_signatures');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_signatures', true);
    }
}
