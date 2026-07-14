<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Key-value store for Service Desk settings.
 *
 * These are the knobs the SuperAdmin controls and the ServiceDesk role
 * consumes: import ceilings, batch pacing, GLPI target entity/requester,
 * which plugin containers to include, and whether missing catalog values
 * may be auto-created during import. Mirrors the provisioning_settings
 * pattern; managed through ServiceDeskSettingsModel.
 */
class CreateServiceDeskSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('servicedesk_settings');

        // Seed default rows so updates always work (no insert needed later).
        $now = date('Y-m-d H:i:s');
        $this->db->table('servicedesk_settings')->insertBatch([
            // Import ceiling per uploaded file (0 = unlimited).
            ['key' => 'import_max_rows',        'value' => '500', 'updated_at' => $now],
            // Batch pacing for the async worker.
            ['key' => 'import_batch_size',      'value' => '30',  'updated_at' => $now],
            ['key' => 'import_batch_pause_sec', 'value' => '2',   'updated_at' => $now],
            // GLPI ticket defaults.
            ['key' => 'glpi_entities_id',       'value' => '0',   'updated_at' => $now],
            ['key' => 'glpi_requester_user_id', 'value' => '0',   'updated_at' => $now],
            // Comma-separated plugin container ids to include (empty = all active Ticket containers).
            ['key' => 'included_container_ids',  'value' => '',    'updated_at' => $now],
            // Whether unmatched dropdown values are auto-created in GLPI during import.
            ['key' => 'autocreate_catalog_values', 'value' => '0', 'updated_at' => $now],
            // Master switch: import is available to the ServiceDesk role only when enabled.
            ['key' => 'import_enabled',         'value' => '1',   'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_settings', true);
    }
}
