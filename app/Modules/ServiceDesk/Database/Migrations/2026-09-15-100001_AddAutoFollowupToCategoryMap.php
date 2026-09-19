<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a per-category flag to servicedesk_category_map that marks which GLPI
 * ITIL categories are eligible for the auto-seguimiento worker.
 *
 * Matching is by subtree (same convention as backlog_regional/backlog_idc/
 * backlog_cliente/audit_ids_tab): a ticket qualifies when its category equals
 * a flagged category or descends from one. Empty flag set = feature effectively
 * has no categories to work on (unlike the backlog flags, there is no "all
 * categories" fallback here — auto-seguimiento must be opted into explicitly).
 */
class AddAutoFollowupToCategoryMap extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('autofollowup_enabled', 'servicedesk_category_map')) {
            $this->forge->addColumn('servicedesk_category_map', [
                'autofollowup_enabled' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'audit_ids_tab',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('autofollowup_enabled', 'servicedesk_category_map')) {
            $this->forge->dropColumn('servicedesk_category_map', 'autofollowup_enabled');
        }
    }
}
