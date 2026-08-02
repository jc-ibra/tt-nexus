<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Key-value store for HelpdeskSupervisor settings. Mirrors the
 * servicedesk_settings pattern: plain key -> value, no is_encrypted column;
 * secret values (glpi_db_password) are stored as ciphertext produced by
 * CredentialCipher and the accessor knows which keys are secret.
 *
 * By default the module reuses Provisioning's GLPI connection
 * (glpi_db_reuse_provisioning = 1); the own-connection keys are reserved for a
 * future deployment where the audited GLPI differs from Provisioning's.
 */
class CreateHelpdeskSupervisorSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('helpdesk_supervisor_settings');

        $now = date('Y-m-d H:i:s');
        $this->db->table('helpdesk_supervisor_settings')->insertBatch([
            // --- GLPI connection (reused from Provisioning by default) ---
            ['key' => 'glpi_db_reuse_provisioning', 'value' => '1', 'updated_at' => $now],
            ['key' => 'glpi_db_host',               'value' => '',  'updated_at' => $now],
            ['key' => 'glpi_db_port',               'value' => '3306', 'updated_at' => $now],
            ['key' => 'glpi_db_name',               'value' => '',  'updated_at' => $now],
            ['key' => 'glpi_db_user',               'value' => '',  'updated_at' => $now],
            ['key' => 'glpi_db_password',           'value' => '',  'updated_at' => $now], // ciphertext when set
            // --- Audit behavior ---
            ['key' => 'audit_auto_run',             'value' => '0', 'updated_at' => $now], // reserved: scheduled audit
            ['key' => 'business_days_abandonment',  'value' => '5', 'updated_at' => $now], // KPI 4 threshold (business days)
            ['key' => 'opening_date_tolerance_sec', 'value' => '60', 'updated_at' => $now], // rule opening_date_default
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('helpdesk_supervisor_settings', true);
    }
}
