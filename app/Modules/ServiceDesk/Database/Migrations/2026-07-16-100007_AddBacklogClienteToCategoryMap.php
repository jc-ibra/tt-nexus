<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a per-category flag marking which GLPI ITIL categories are counted in the
 * backlog report's "POR CLIENTE" table. The client name itself comes from the
 * existing `cliente` column (CLIENTE para el título), resolved by subtree.
 *
 * Independent of backlog_regional/backlog_idc: a separate flag is needed because
 * internal AD categories also carry a `cliente` (for title homologation) that
 * should NOT appear as a client in the report. Empty flagged set = all.
 */
class AddBacklogClienteToCategoryMap extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('backlog_cliente', 'servicedesk_category_map')) {
            $this->forge->addColumn('servicedesk_category_map', [
                'backlog_cliente' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'backlog_idc',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('backlog_cliente', 'servicedesk_category_map')) {
            $this->forge->dropColumn('servicedesk_category_map', 'backlog_cliente');
        }
    }
}
