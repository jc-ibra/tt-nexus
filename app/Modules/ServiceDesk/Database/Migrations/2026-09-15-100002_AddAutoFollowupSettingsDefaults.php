<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default key/value rows for the auto-seguimiento worker into
 * servicedesk_settings. Idempotent: only inserts keys that are still missing.
 *
 * Auto-seguimiento periodically scans open GLPI tickets (categories flagged in
 * servicedesk_category_map.autofollowup_enabled), and for eligible ones sends a
 * follow-up email to the assignee (CC the requester), writes an ITILFollowup
 * note on the ticket, and optionally opens/reuses a MailDispatch conversation
 * so the reply is tracked. See app/Modules/ServiceDesk/Services/AutoFollowupService.php.
 */
class AddAutoFollowupSettingsDefaults extends Migration
{
    /** @var array<string,string> key => default value */
    private array $defaults = [
        // Master switch.
        'autofollowup_enabled' => '0',
        // Cooldown: minimum hours between two follow-ups on the SAME ticket.
        'autofollowup_interval_hours' => '48',
        // "En espera" (pending) tickets only follow up after this many days in
        // that status — they usually already have a manual reason to wait.
        'autofollowup_pending_threshold_days' => '5',
        // Whether a MailDispatch conversation is opened/reused per follow-up.
        'autofollowup_create_conversation' => '0',
        // Sender identity, independent from the backlog report's.
        'autofollowup_from_name'  => 'Mesa de Ayuda',
        'autofollowup_from_email' => '',
        // Templates (placeholders: {{folio}} {{asunto}} {{categoria}} {{asignado}}
        // {{solicitante}} {{estado}} {{dias_abierto}}).
        'autofollowup_email_subject' => 'Seguimiento ticket #{{folio}} · {{asunto}}',
        'autofollowup_email_body'    => "<p>Hola {{asignado}},</p><p>El ticket <strong>#{{folio}} - {{asunto}}</strong> (categoría {{categoria}}) sigue {{estado}} desde hace {{dias_abierto}} día(s).</p><p>¿Nos ayudas con un estatus?</p><p>Gracias.<br>Mesa de Ayuda</p>",
        'autofollowup_glpi_note'     => 'Nexus envió un correo de seguimiento solicitando estatus a {{asignado}} (con copia a {{solicitante}}).',
        'autofollowup_note_is_private' => '1',
    ];

    public function up(): void
    {
        $table = $this->db->table('servicedesk_settings');
        $now   = date('Y-m-d H:i:s');

        foreach ($this->defaults as $key => $value) {
            $exists = $this->db->table('servicedesk_settings')
                ->where('key', $key)
                ->countAllResults();
            if ($exists === 0) {
                $table->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('servicedesk_settings')
            ->whereIn('key', array_keys($this->defaults))
            ->delete();
    }
}
