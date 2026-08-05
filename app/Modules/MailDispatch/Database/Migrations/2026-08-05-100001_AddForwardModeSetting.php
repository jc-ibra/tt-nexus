<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the "forward mode" setting. When the IMAP mailbox receives every message
 * through a forwarding rule, the SMTP sender is always the forwarder, so the real
 * requester lives inside the body (the De:/From: block). With this on, ingestion
 * parses that block and uses the original sender as the requester.
 *
 * Row-only migration; the settings table already exists. Idempotent.
 */
class AddForwardModeSetting extends Migration
{
    public function up(): void
    {
        $table  = $this->db->table('maildispatch_settings');
        $exists = $table->where('key', 'treat_as_forwards')->countAllResults(false) > 0;
        $table->resetQuery();
        if (! $exists) {
            $table->insert(['key' => 'treat_as_forwards', 'value' => '0', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down(): void
    {
        $this->db->table('maildispatch_settings')->where('key', 'treat_as_forwards')->delete();
    }
}
