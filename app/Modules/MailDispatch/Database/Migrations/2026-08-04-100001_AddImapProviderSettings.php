<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the settings that let MailDispatch operate against a plain IMAP mailbox
 * instead of Microsoft Graph. A single `provider` switch selects the backend
 * ('graph' | 'imap'); the IMAP block is the read side (a mailbox that receives
 * every message via a forwarding rule) and the SMTP block is the send side used
 * when replying from Nexus in IMAP mode.
 *
 * Row-only migration: the settings table already exists (100001_Create…). Each
 * key is inserted only when missing, so it is safe and idempotent on any DB.
 * The passwords (imap_password / smtp_password) are stored encrypted through
 * CredentialCipher, handled by MailDispatchSettingsModel.
 */
class AddImapProviderSettings extends Migration
{
    public function up(): void
    {
        $now  = date('Y-m-d H:i:s');
        $keys = [
            // --- Backend selector: 'graph' (default, existing) or 'imap' ---
            'provider' => 'graph',

            // --- IMAP (read) ---
            'imap_host'          => '',
            'imap_port'          => '993',
            'imap_encryption'    => 'ssl',   // 'ssl' | 'tls' | 'none'
            'imap_validate_cert' => '1',
            'imap_username'      => '',
            'imap_password'      => '',      // encrypted at rest
            'imap_folder'        => 'INBOX',

            // --- SMTP (send) — used for reply-from-Nexus in IMAP mode ---
            'smtp_host'          => '',
            'smtp_port'          => '587',
            'smtp_encryption'    => 'tls',   // 'tls' | 'ssl' | 'none'
            'smtp_username'      => '',
            'smtp_password'      => '',      // encrypted at rest
            'smtp_from_email'    => '',
            'smtp_from_name'     => '',
        ];

        $table = $this->db->table('maildispatch_settings');
        foreach ($keys as $key => $value) {
            $exists = $table->where('key', $key)->countAllResults(false) > 0;
            $table->resetQuery();
            if (! $exists) {
                $table->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'provider',
            'imap_host', 'imap_port', 'imap_encryption', 'imap_validate_cert',
            'imap_username', 'imap_password', 'imap_folder',
            'smtp_host', 'smtp_port', 'smtp_encryption',
            'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
        ];
        $this->db->table('maildispatch_settings')->whereIn('key', $keys)->delete();
    }
}
