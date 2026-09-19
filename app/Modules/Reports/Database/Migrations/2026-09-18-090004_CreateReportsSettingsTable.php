<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Key/value settings, same shape as provisioning_settings /
 * helpdesk_supervisor_settings: no ENCRYPTED_KEYS needed here since Reports
 * never stores its own GLPI credentials — it reuses Provisioning's
 * GlpiDbConnection.
 */
class CreateReportsSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('reports_settings');

        $now = date('Y-m-d H:i:s');
        $this->db->table('reports_settings')->insertBatch([
            // JSON map: logical key => {container_id, field}. Each logical
            // key (regional, estado_geo, municipio, sucursal, ids) can live
            // in a DIFFERENT plugin Additional Fields container — e.g. the
            // client-data fields live in "Clientes Externos" but the
            // technician identity (IDS) lives in its own "IDS" container.
            // A key missing from this map (or an id/field GlpiSchemaIntrospector
            // can no longer resolve) makes GlpiTicketsProvider report that
            // single breakdown as unavailable, not the whole snapshot.
            ['key' => 'glpi_field_bindings', 'value' => '{}', 'updated_at' => $now],

            ['key' => 'sla_hours',                     'value' => '24', 'updated_at' => $now],
            ['key' => 'trend_months',                  'value' => '12', 'updated_at' => $now],

            ['key' => 'ai_enabled',                    'value' => '0',  'updated_at' => $now],
            ['key' => 'ai_model',                      'value' => 'claude-sonnet-5', 'updated_at' => $now],
            // Reuses HelpdeskSupervisor's key by default (which itself may
            // reuse ServiceDesk's) so the API key is entered once per platform;
            // 'ai_api_key' below is only used when this is '0'.
            ['key' => 'ai_reuse_helpdesk_supervisor',  'value' => '1',  'updated_at' => $now],
            ['key' => 'ai_api_key',                    'value' => '',   'updated_at' => $now], // ciphertext when set

            ['key' => 'email_recipients',              'value' => '',   'updated_at' => $now],
            ['key' => 'email_sender_name',              'value' => 'Reportes tt-nexus', 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('reports_settings', true);
    }
}
