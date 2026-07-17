<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a per-category flag to servicedesk_category_map that marks which GLPI
 * ITIL categories are counted in the backlog report's "POR REGIONAL" table.
 *
 * Matching is by subtree: a ticket counts when its category equals a flagged
 * category or descends from one (so flagging "OP > CE" captures everything under
 * it). When no category is flagged, all categories count (backward compatible,
 * mirrors the is_supported convention).
 */
class AddBacklogRegionalToCategoryMap extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('backlog_regional', 'servicedesk_category_map')) {
            $this->forge->addColumn('servicedesk_category_map', [
                'backlog_regional' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'is_supported',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('backlog_regional', 'servicedesk_category_map')) {
            $this->forge->dropColumn('servicedesk_category_map', 'backlog_regional');
        }
    }
}
