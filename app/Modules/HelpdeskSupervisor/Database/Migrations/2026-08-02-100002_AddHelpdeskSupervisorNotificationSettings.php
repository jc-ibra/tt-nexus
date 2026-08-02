<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase 2 settings: IA notification config. The API key is stored as ciphertext
 * (CredentialCipher) and, by default, reused from servicedesk_settings so the
 * supervisor does not re-enter it.
 */
class AddHelpdeskSupervisorNotificationSettings extends Migration
{
    public function up(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [
            ['key' => 'ai_api_key',                   'value' => '',                  'updated_at' => $now], // ciphertext when set
            ['key' => 'ai_api_key_reuse_servicedesk', 'value' => '1',                 'updated_at' => $now],
            ['key' => 'ai_model',                     'value' => 'claude-haiku-4-5',  'updated_at' => $now],
            ['key' => 'ai_max_tokens',                'value' => '2048',              'updated_at' => $now],
            ['key' => 'notification_sender_name',     'value' => '',                  'updated_at' => $now],
            ['key' => 'notification_sender_email',    'value' => '',                  'updated_at' => $now],
            ['key' => 'notification_cc',              'value' => '',                  'updated_at' => $now],
        ];

        $table = $this->db->table('helpdesk_supervisor_settings');
        foreach ($rows as $row) {
            $exists = $table->where('key', $row['key'])->countAllResults();
            $table  = $this->db->table('helpdesk_supervisor_settings');
            if (! $exists) {
                $table->insert($row);
                $table = $this->db->table('helpdesk_supervisor_settings');
            }
        }
    }

    public function down(): void
    {
        $this->db->table('helpdesk_supervisor_settings')
            ->whereIn('key', [
                'ai_api_key', 'ai_api_key_reuse_servicedesk', 'ai_model', 'ai_max_tokens',
                'notification_sender_name', 'notification_sender_email', 'notification_cc',
            ])->delete();
    }
}
