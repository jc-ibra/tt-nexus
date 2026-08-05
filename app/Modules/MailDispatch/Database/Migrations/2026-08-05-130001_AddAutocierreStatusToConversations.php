<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the "autocierre" value to the conversations status ENUM. Without it,
 * MySQL (non-strict) silently stored an empty string when the auto-triage rule
 * routed a conversation to autocierre, so it never left the main queue.
 */
class AddAutocierreStatusToConversations extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE maildispatch_conversations MODIFY COLUMN status "
            . "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','autocierre','cerrada') "
            . "NOT NULL DEFAULT 'nueva'"
        );
    }

    public function down(): void
    {
        // Move any autocierre rows to a valid legacy status before shrinking the enum.
        $this->db->query("UPDATE maildispatch_conversations SET status = 'nueva' WHERE status = 'autocierre'");
        $this->db->query(
            "ALTER TABLE maildispatch_conversations MODIFY COLUMN status "
            . "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','cerrada') "
            . "NOT NULL DEFAULT 'nueva'"
        );
    }
}
