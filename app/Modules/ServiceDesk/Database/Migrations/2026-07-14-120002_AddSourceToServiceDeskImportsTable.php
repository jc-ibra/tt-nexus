<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tracks where an import job originated: the bulk importer ('import') or the
 * AI ticket creator ('ai_creator'). Shown in the history so operators can tell
 * them apart.
 */
class AddSourceToServiceDeskImportsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('source', 'servicedesk_imports')) {
            return;
        }
        $this->forge->addColumn('servicedesk_imports', [
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'import',
                'after'      => 'status',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('source', 'servicedesk_imports')) {
            $this->forge->dropColumn('servicedesk_imports', 'source');
        }
    }
}
