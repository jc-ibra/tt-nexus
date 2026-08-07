<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the `sync_since` setting: an optional import cutoff. When set, the initial
 * (or --full) sync only pulls messages received at/after this datetime, so a
 * very large mailbox is not walked from the beginning of time. Empty = no cutoff
 * (import everything, the original behavior).
 *
 * Stored as 'Y-m-d H:i:s' in the app timezone; consumed server-side per provider
 * (Graph $filter / IMAP SINCE) plus a defensive skip at ingestion time.
 */
class AddSyncSinceSetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('maildispatch_settings')->where('key', 'sync_since')->countAllResults();
        if (! $exists) {
            $this->db->table('maildispatch_settings')->insert([
                'key'        => 'sync_since',
                'value'      => '',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('maildispatch_settings')->where('key', 'sync_since')->delete();
    }
}
