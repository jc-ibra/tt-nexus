<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit trail of auto-seguimiento attempts. One row per ticket per run, used to
 * (a) show the admin what went out and (b) enforce the per-ticket cooldown
 * (autofollowup_interval_hours) — the worker checks the latest 'sent' row for a
 * ticket before considering it again.
 */
class CreateServiceDeskFollowupRunsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ticket_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'glpi_category_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // sent | failed | skipped
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'sent'],
            'assignee_email'    => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'requester_email'   => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'glpi_followup_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'conversation_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'trigger'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'scheduled'], // scheduled | manual
            'error'             => ['type' => 'TEXT', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('ticket_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('servicedesk_followup_runs');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_followup_runs', true);
    }
}
