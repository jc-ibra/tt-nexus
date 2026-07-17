<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a per-category flag marking which GLPI ITIL categories the "Sin IDC"
 * metric applies to. Independent of backlog_regional: the IDC concept and the
 * regional table can have different category scopes.
 *
 * Matching is by subtree (a ticket counts when its category equals or descends
 * from a flagged one). Empty flagged set = all categories (backward compatible).
 * When set, the "Sin IDC" KPI, its percentage, and the per-regional Sin IDC
 * column only consider tickets within the flagged scope.
 */
class AddBacklogIdcToCategoryMap extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('backlog_idc', 'servicedesk_category_map')) {
            $this->forge->addColumn('servicedesk_category_map', [
                'backlog_idc' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'backlog_regional',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('backlog_idc', 'servicedesk_category_map')) {
            $this->forge->dropColumn('servicedesk_category_map', 'backlog_idc');
        }
    }
}
