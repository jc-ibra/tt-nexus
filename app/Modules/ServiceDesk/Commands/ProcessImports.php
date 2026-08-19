<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Commands;

use App\Modules\Core\Services\RunLock;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Processes queued Service Desk bulk jobs. Run via cron; a lock file prevents
 * overlapping runs (writes to GLPI must be serialized).
 *
 * One queue, two engines, dispatched by servicedesk_imports.mode:
 *   'create' -> TicketBulkImporter  (alta masiva)
 *   'update' -> TicketBulkUpdater   (plancha de datos + cierre masivo)
 * Sharing the queue is deliberate: it keeps both engines behind the same lock,
 * so an import and an update can never hit GLPI at the same time.
 *
 *   php spark servicedesk:process-imports
 *   * * * * * cd /path && php spark servicedesk:process-imports >> /dev/null 2>&1
 */
class ProcessImports extends BaseCommand
{
    protected $group       = 'ServiceDesk';
    protected $name        = 'servicedesk:process-imports';
    protected $description = 'Procesa los trabajos encolados de alta masiva y de actualización/cierre masivo de tickets.';
    protected $usage       = 'servicedesk:process-imports [--max <n>]';
    protected $options     = [
        '--max' => 'Máximo de trabajos a procesar en esta corrida (default: todos los pendientes).',
    ];

    /**
     * Una corrida más larga que esto se asume muerta y su lock se recupera.
     * Amplio: una importación masiva puede tardar bastante.
     */
    private const LOCK_STALE_SECONDS = 7200;

    public function run(array $params): void
    {
        $max = (int) (CLI::getOption('max') ?: 0);

        $lock = RunLock::acquire('servicedesk_imports', self::LOCK_STALE_SECONDS);
        if ($lock === null) {
            CLI::write('Ya hay una corrida en curso (lock presente). Saliendo.', 'yellow');
            return;
        }

        try {
            $imports = new ServiceDeskImportModel();

            $done = 0;
            while (($job = $imports->nextPending()) !== null) {
                $id     = (int) $job['id'];
                $mode   = (string) ($job['mode'] ?? 'create');
                $isUpd  = $mode === 'update';
                $engine = $isUpd ? service('serviceDeskUpdater') : service('serviceDeskImporter');
                $label  = $isUpd
                    ? ((int) ($job['dry_run'] ?? 0) === 1 ? 'simulación' : 'actualización')
                    : 'importación';

                CLI::write(sprintf('[%s] Procesando %s #%d (%s)…', date('H:i:s'), $label, $id, $job['source_filename'] ?? ''), 'cyan');

                try {
                    $engine->run($id);
                    $fresh = $imports->find($id);
                    CLI::write(sprintf(
                        '  #%d: %s — %s ok, %s sin cambios, %s con problema.',
                        $id,
                        $fresh['status'] ?? 'ready',
                        $fresh['succeeded_rows'] ?? 0,
                        $fresh['skipped_rows'] ?? 0,
                        $fresh['failed_rows'] ?? 0,
                    ), 'green');
                } catch (\Throwable $e) {
                    CLI::write("  #{$id}: error — " . $e->getMessage(), 'red');
                }

                $done++;
                if ($max > 0 && $done >= $max) {
                    break;
                }
            }

            if ($done === 0) {
                CLI::write('No hay trabajos pendientes.', 'yellow');
            } else {
                CLI::write(sprintf('[%s] Listo. %d trabajo(s) procesado(s).', date('H:i:s'), $done), 'green');
            }
        } finally {
            $lock->release();
        }
    }
}
