<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Index backing the duplicate-delivery check.
 *
 * When two recipients of the same mail both redirect into the shared mailbox,
 * each delivered copy arrives with its own Message-ID, so the existing dedupe
 * (by internet_message_id) cannot catch them. The second copy is recognised by
 * sender + subject + the exact received instant, and that lookup runs for every
 * ingested message, so it needs an index.
 */
class AddDeliveryDedupeIndexToMessages extends Migration
{
    private const INDEX = 'idx_md_msg_delivery';

    public function up(): void
    {
        foreach ($this->db->getIndexData('maildispatch_messages') as $idx) {
            if ($idx->name === self::INDEX) {
                return;
            }
        }

        $this->db->query(
            'CREATE INDEX ' . self::INDEX . ' ON maildispatch_messages (from_email, received_at)'
        );
    }

    public function down(): void
    {
        foreach ($this->db->getIndexData('maildispatch_messages') as $idx) {
            if ($idx->name === self::INDEX) {
                $this->db->query('DROP INDEX ' . self::INDEX . ' ON maildispatch_messages');
                return;
            }
        }
    }
}
