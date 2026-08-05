<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Thread tokens per message: every RFC Message-ID a message carries — its own
 * internet_message_id plus each id in its In-Reply-To / References headers.
 *
 * This powers reference-overlap threading: two messages belong to the same
 * conversation when they share any token. It threads forwarded chains (RV:/FW:)
 * whose In-Reply-To points at external ids we never stored, but whose References
 * share the chain's ancestors — the common real-world duplication cause.
 *
 * The table is backfilled from existing messages so grouping works immediately.
 */
class CreateMailDispatchMessageRefsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'message_id' => ['type' => 'INT', 'unsigned' => true],
            // A single <message-id> token (angle brackets kept for exact match).
            'ref_id'     => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['message_id', 'ref_id']);
        $this->forge->addKey('ref_id');
        $this->forge->addForeignKey('message_id', 'maildispatch_messages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('maildispatch_message_refs');

        $this->backfill();
    }

    /** Populates tokens for every existing message (idempotent via ignore). */
    private function backfill(): void
    {
        $messages = $this->db->table('maildispatch_messages')
            ->select('id, internet_message_id, in_reply_to, references_header')
            ->get()->getResultArray();

        $rows = [];
        foreach ($messages as $m) {
            $tokens = $this->tokensOf(
                (string) ($m['internet_message_id'] ?? ''),
                (string) ($m['in_reply_to'] ?? ''),
                (string) ($m['references_header'] ?? '')
            );
            foreach ($tokens as $t) {
                $rows[] = ['message_id' => (int) $m['id'], 'ref_id' => $t];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db->table('maildispatch_message_refs')->ignore(true)->insertBatch($chunk);
        }
    }

    /** Extracts the unique <...> tokens from a message's threading fields. */
    private function tokensOf(string $ownId, string $inReplyTo, string $references): array
    {
        $tokens = [];
        foreach ([$ownId, $inReplyTo, $references] as $raw) {
            if ($raw === '') {
                continue;
            }
            if (preg_match_all('/<[^>]+>/', $raw, $mm)) {
                foreach ($mm[0] as $t) {
                    $t = trim($t);
                    if ($t !== '') {
                        $tokens[$t] = true;
                    }
                }
            }
        }
        return array_keys($tokens);
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_message_refs', true);
    }
}
