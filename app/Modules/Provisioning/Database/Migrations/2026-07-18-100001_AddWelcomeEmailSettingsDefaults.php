<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the admin-configurable welcome-email settings into
 * provisioning_settings so the SuperAdmin can toggle the send and edit the
 * key copy blocks from /admin/provisioning/settings. Idempotent: only inserts
 * a key that is not already present, so it is safe to re-run and never
 * overwrites values an admin already customized.
 *
 * The system login URLs are intentionally NOT stored here: the welcome email
 * reads them from each system's base_url (provisioning_systems), the same URL
 * shown at /admin/provisioning/systems.
 */
class AddWelcomeEmailSettingsDefaults extends Migration
{
    /** Default values; must mirror WelcomeMailService::defaults(). */
    private const DEFAULTS = [
        'welcome_email_enabled'         => '1',
        'welcome_email_subject'         => 'Bienvenido: tus accesos y contrasena temporal',
        'welcome_email_org_name'        => 'Trantor Technologies',
        'welcome_email_help_desk_email' => 'mesadeayuda@trantortechnologies.mx',
        'welcome_email_hero_eyebrow'    => 'Te damos la bienvenida',
        'welcome_email_hero_title'      => 'Bienvenido a {org}',
        'welcome_email_hero_intro'      => 'Nos da mucho gusto tenerte en el equipo. Ya dejamos listos tus accesos para que empieces con el pie derecho.',
        'welcome_email_security_notice' => 'esta contrasena es temporal. Por tu seguridad, cambiala lo antes posible en cada sistema.',
        'welcome_email_support_intro'   => 'Si tienes algun problema con tus accesos, reportalo directamente a la mesa de ayuda central:',
        'welcome_email_footer'          => 'Este mensaje se genero automaticamente al crear tus cuentas. No es necesario responderlo.',
    ];

    public function up(): void
    {
        $now   = date('Y-m-d H:i:s');
        $table = $this->db->table('provisioning_settings');

        foreach (self::DEFAULTS as $key => $value) {
            $exists = $this->db->table('provisioning_settings')->where('key', $key)->countAllResults();
            if ($exists === 0) {
                $table->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('provisioning_settings')
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->delete();
    }
}
