<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Registers the TechBot module and wires access (spec §14).
 *
 * - Creates the module row in core_modules.
 * - Grants module access to SuperAdmin (config + supervision).
 * - Ensures the default settings rows exist in techbot_settings.
 *
 * Idempotent: safe to run repeatedly.
 */
class TechBotModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ----------------------------------------------------------------
        // Module registry
        // ----------------------------------------------------------------
        $existing = $this->db->table('core_modules')->where('key', 'techbot')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "TechBotModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'techbot',
                'name'        => 'TechBot',
                'description' => 'Canal de Telegram para documentacion de tickets por tecnicos de campo.',
                'route_base'  => 'techbot',
                'icon'        => 'bot',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "TechBotModuleSeeder: module created (id={$moduleId}).\n";
        }

        // ----------------------------------------------------------------
        // Grant access to SuperAdmin
        // ----------------------------------------------------------------
        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $link = $this->db->table('core_role_modules')
                ->where('role_id', (int) $superAdmin->id)
                ->where('module_id', $moduleId)
                ->get()->getRow();
            if (! $link) {
                $this->db->table('core_role_modules')->insert([
                    'role_id'   => (int) $superAdmin->id,
                    'module_id' => $moduleId,
                ]);
                echo "TechBotModuleSeeder: granted module access to SuperAdmin.\n";
            }
        }

        // ----------------------------------------------------------------
        // Default settings (only insert missing keys — never clobber values)
        // ----------------------------------------------------------------
        $defaults = [
            'telegram_bot_username'             => '',
            'bot_enabled'                       => '0',
            'ai_formatting_enabled'             => '0',
            'ai_max_tokens'                     => '1024',
            'ai_system_prompt'                  => '',
            'welcome_message'                   => 'Tu cuenta ha sido vinculada exitosamente. Ya puedes consultar y documentar tus tickets desde aqui.',
            'require_photo_on_resolution'       => '0',
            'require_visto_bueno_on_resolution' => '1',
            'allow_resolucion_arbitraria'       => '0',
        ];

        foreach ($defaults as $key => $value) {
            $exists = $this->db->table('techbot_settings')->where('key', $key)->countAllResults();
            if (! $exists) {
                $this->db->table('techbot_settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'updated_at' => $now,
                ]);
            }
        }
        echo "TechBotModuleSeeder: default settings ensured.\n";
    }
}
