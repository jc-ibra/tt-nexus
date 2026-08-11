<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marks conversations the help desk itself started (an outbound mail that
 * replies to nothing: broadcasts like the daily backlog report, proactive
 * outreach).
 *
 * Those threads have no requester — the counterpart stored in requester_* is
 * just the first To, kept because replies need a destination — so they must not
 * sit in the "Sin asignar" work queue pretending to be customer mail.
 *
 * The flag clears itself the moment someone actually answers: the first inbound
 * message turns the thread into a real conversation and it rejoins the queue.
 */
class AddOutboundOnlyToConversations extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('outbound_only', 'maildispatch_conversations')) {
            return;
        }

        $this->forge->addColumn('maildispatch_conversations', [
            'outbound_only' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'agent_id',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('outbound_only', 'maildispatch_conversations')) {
            $this->forge->dropColumn('maildispatch_conversations', 'outbound_only');
        }
    }
}
