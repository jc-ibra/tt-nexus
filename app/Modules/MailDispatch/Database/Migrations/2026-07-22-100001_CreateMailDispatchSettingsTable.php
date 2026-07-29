<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Key-value store for MailDispatch settings.
 *
 * All configuration the SuperAdmin controls: Microsoft Graph credentials
 * (the client secret is stored encrypted via CredentialCipher, never in .env),
 * the shared mailbox address, sync control, SLA thresholds, and the phase-3
 * "send from Nexus" master switch. Mirrors provisioning_settings /
 * servicedesk_settings; managed through MailDispatchSettingsModel.
 */
class CreateMailDispatchSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('maildispatch_settings');

        // Seed default rows so updates always work (no insert needed later).
        $now = date('Y-m-d H:i:s');
        $this->db->table('maildispatch_settings')->insertBatch([
            // --- Microsoft Graph app credentials (client credentials flow) ---
            ['key' => 'graph_tenant_id',     'value' => '', 'updated_at' => $now],
            ['key' => 'graph_client_id',     'value' => '', 'updated_at' => $now],
            // Stored encrypted through CredentialCipher.
            ['key' => 'graph_client_secret', 'value' => '', 'updated_at' => $now],

            // --- Shared mailbox to sync (single mailbox in this version) ---
            ['key' => 'mailbox_address',     'value' => '', 'updated_at' => $now],

            // --- Sync control ---
            ['key' => 'sync_enabled',        'value' => '0',  'updated_at' => $now],
            ['key' => 'sync_page_size',      'value' => '50', 'updated_at' => $now],

            // --- SLA thresholds (minutes) for visual alerts + metrics ---
            ['key' => 'sla_unassigned_minutes',     'value' => '30',  'updated_at' => $now],
            ['key' => 'sla_first_response_minutes', 'value' => '120', 'updated_at' => $now],

            // --- Phase 3 master switch: reply from Nexus (off by default) ---
            ['key' => 'send_from_nexus_enabled', 'value' => '0', 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_settings', true);
    }
}
