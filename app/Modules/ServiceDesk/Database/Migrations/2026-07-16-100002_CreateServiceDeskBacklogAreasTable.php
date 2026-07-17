<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Maps a GLPI ROOT ITIL category to a business area for the backlog report.
 *
 * The report splits the whole open backlog into Administración / Operaciones.
 * The SuperAdmin assigns each root category (glpi_itilcategories with no parent)
 * to one area here; a ticket inherits the area of its category's root. Rows are
 * keyed by the GLPI category id (external, not an FK — GLPI is a separate DB).
 */
class CreateServiceDeskBacklogAreasTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'itilcategory_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // '' = unassigned; otherwise an area key from ServiceDesk::$backlogAreas.
            'area'            => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
            'category_name'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('itilcategory_id');
        $this->forge->createTable('servicedesk_backlog_areas');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_backlog_areas', true);
    }
}
