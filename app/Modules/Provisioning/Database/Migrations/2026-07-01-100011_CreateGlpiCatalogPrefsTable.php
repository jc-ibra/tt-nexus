<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nexus-only presentation overrides for GLPI dropdown catalogs. Lets the admin
 * reclassify (group), relabel or HIDE a dropdown in the Nexus UI without ever
 * touching GLPI. Keyed by the GLPI table name; only tables that differ from
 * their default get a row (absence = use static registry defaults).
 */
class CreateGlpiCatalogPrefsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'table_name'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'label'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'classification' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_hidden'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('table_name');
        $this->forge->createTable('provisioning_glpi_catalog_prefs');
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_glpi_catalog_prefs', true);
    }
}
