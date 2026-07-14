<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nexus-side mapping over GLPI ITIL categories, controlled by the SuperAdmin:
 *   - is_supported: whether the category is offered in the import template.
 *   - cliente: the CLIENTE label used to homologate the ticket title
 *              (CLIENTE - SUCURSAL - TITULO).
 *
 * Keyed by the GLPI category id (glpi_itilcategories.id). category_name is a
 * snapshot of the completename for display stability.
 */
class CreateServiceDeskCategoryMapTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'glpi_category_id' => ['type' => 'INT', 'unsigned' => true],
            'category_name'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'cliente'          => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'is_supported'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('glpi_category_id');
        $this->forge->createTable('servicedesk_category_map');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_category_map', true);
    }
}
