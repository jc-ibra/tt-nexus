<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Key-value store for TechBot settings.
 *
 * Same pattern as servicedesk_settings / mailboxes_settings. Holds the Telegram
 * bot token (encrypted), the webhook secret (encrypted), operational toggles and
 * the message/AI knobs. Managed through TechBotSettingsModel + TechBotSettingsService.
 */
class CreateTechBotSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('techbot_settings');

        // Seed default rows so updates always work (no insert needed later).
        // Secrets (telegram_bot_token, telegram_webhook_secret) are intentionally
        // left absent; they are written encrypted the first time they are saved.
        $now = date('Y-m-d H:i:s');
        $this->db->table('techbot_settings')->insertBatch([
            ['key' => 'telegram_bot_username',           'value' => '',  'updated_at' => $now],
            ['key' => 'bot_enabled',                     'value' => '0', 'updated_at' => $now],
            ['key' => 'ai_formatting_enabled',           'value' => '0', 'updated_at' => $now],
            ['key' => 'ai_max_tokens',                   'value' => '1024', 'updated_at' => $now],
            ['key' => 'ai_system_prompt',                'value' => '',  'updated_at' => $now],
            ['key' => 'welcome_message',                 'value' => 'Tu cuenta ha sido vinculada exitosamente. Ya puedes consultar y documentar tus tickets desde aqui.', 'updated_at' => $now],
            ['key' => 'require_photo_on_resolution',     'value' => '0', 'updated_at' => $now],
            ['key' => 'require_visto_bueno_on_resolution', 'value' => '1', 'updated_at' => $now],
            // Whether the "resolucion arbitraria" template is offered to technicians.
            ['key' => 'allow_resolucion_arbitraria',     'value' => '0', 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('techbot_settings', true);
    }
}
