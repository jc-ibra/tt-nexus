<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Turns servicedesk_imports into the job table for BOTH engines:
 *
 *   mode = 'create' -> TicketBulkImporter  (alta masiva, el flujo original)
 *   mode = 'update' -> TicketBulkUpdater   (plancha de datos + cierre masivo)
 *
 * `source` sigue diciendo de DÓNDE vino el trabajo (import / ai_creator);
 * `mode` dice QUÉ hace. Son ejes distintos y por eso no se reusa `source`.
 *
 * skipped_rows: filas sin cambios que aplicar. El importador ya omitía filas
 * (las que traían TICKET_ID) sin contarlas en ningún lado; ahora quedan visibles.
 * dry_run: simulación, calcula el diff y escribe el reporte sin tocar GLPI.
 *
 * Idempotente por fieldExists: convive con db:baseline.
 */
class AddUpdateModeToServiceDeskImports extends Migration
{
    private const TABLE = 'servicedesk_imports';

    public function up(): void
    {
        $add = [];

        if (! $this->db->fieldExists('mode', self::TABLE)) {
            $add['mode'] = [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'create',
                'after'      => 'source',
            ];
        }
        if (! $this->db->fieldExists('skipped_rows', self::TABLE)) {
            $add['skipped_rows'] = [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
                'after'    => 'failed_rows',
            ];
        }
        if (! $this->db->fieldExists('dry_run', self::TABLE)) {
            $add['dry_run'] = [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'skipped_rows',
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn(self::TABLE, $add);
        }
    }

    public function down(): void
    {
        foreach (['mode', 'skipped_rows', 'dry_run'] as $column) {
            if ($this->db->fieldExists($column, self::TABLE)) {
                $this->forge->dropColumn(self::TABLE, $column);
            }
        }
    }
}
