<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Keeps the Cc list of every message alongside the To list already stored.
 *
 * The queue only showed the sender, so an agent could not tell who else was
 * addressed or copied before writing a reply — which matters because replies go
 * out to the requester alone (deliberately, to keep the SMTP volume down) and
 * any extra recipient has to be added by hand.
 *
 * Only messages synced after this runs carry a Cc; older rows stay null.
 */
class AddCcRecipientsToMessages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('maildispatch_messages', [
            'cc_recipients' => ['type' => 'TEXT', 'null' => true, 'after' => 'to_recipients'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('maildispatch_messages', 'cc_recipients');
    }
}
