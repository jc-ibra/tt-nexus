<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Key-value store for provisioning-scoped settings.
 *
 * Used to persist the connection to GLPI's external database (host, port,
 * name, user and password) so the GLPI additional-fields catalogs can be
 * managed from Nexus. The password is encrypted at rest by
 * ProvisioningSettingsModel (CredentialCipher / encryption.key).
 */
class CreateProvisioningSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('provisioning_settings');

        // Seed default rows so updates always work (no insert needed later).
        $now = date('Y-m-d H:i:s');
        $this->db->table('provisioning_settings')->insertBatch([
            ['key' => 'glpi_db_host',     'value' => '',     'updated_at' => $now],
            ['key' => 'glpi_db_port',     'value' => '3306', 'updated_at' => $now],
            ['key' => 'glpi_db_name',     'value' => '',     'updated_at' => $now],
            ['key' => 'glpi_db_user',     'value' => '',     'updated_at' => $now],
            ['key' => 'glpi_db_password', 'value' => '',     'updated_at' => $now],
            ['key' => 'glpi_db_enabled',  'value' => '0',    'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_settings', true);
    }
}
