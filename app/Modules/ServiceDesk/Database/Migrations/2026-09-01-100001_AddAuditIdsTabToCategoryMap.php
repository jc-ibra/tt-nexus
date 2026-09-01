<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-category flag: HelpdeskSupervisor audit requires the IDS tab on tickets
 * whose category equals or descends from a flagged root. Empty flagged set =
 * fall back to the built-in category classifier rules.
 */
class AddAuditIdsTabToCategoryMap extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('audit_ids_tab', 'servicedesk_category_map')) {
            $this->forge->addColumn('servicedesk_category_map', [
                'audit_ids_tab' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'backlog_cliente',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('audit_ids_tab', 'servicedesk_category_map')) {
            $this->forge->dropColumn('servicedesk_category_map', 'audit_ids_tab');
        }
    }
}
