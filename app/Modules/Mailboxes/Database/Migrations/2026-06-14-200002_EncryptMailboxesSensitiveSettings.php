<?php

declare(strict_types=1);

namespace App\Modules\Mailboxes\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Clears plaintext values of sensitive settings introduced before encryption
 * was enforced. The admin must re-enter the Mailcow URL and API Key after
 * this migration runs. New saves are encrypted by MailboxesSettingsModel.
 */
class EncryptMailboxesSensitiveSettings extends Migration
{
    private const SENSITIVE_KEYS = ['mailcow_url', 'mailcow_api_key'];

    public function up(): void
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            $this->db->table('mailboxes_settings')
                ->where('key', $key)
                ->update(['value' => '', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down(): void
    {
        // Nothing to restore — plaintext credentials must not be re-introduced.
    }
}
