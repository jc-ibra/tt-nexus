<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Commands;

use App\Modules\Core\Services\RunLock;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Processes queued Service Desk bulk-import jobs. Run via cron; a lock file
 * prevents overlapping runs (imports must be serialized).
 *
 *   php spark servicedesk:process-imports
 *   * * * * * cd /path && php spark servicedesk:process-imports >> /dev/null 2>&1
 */
class ProcessImports extends BaseCommand
{
    protected $group       = 'ServiceDesk';
    protected $name        = 'servicedesk:process-imports';
    protected $description = 'Procesa los trabajos de importación masiva de tickets encolados.';
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
            $imports  = new ServiceDeskImportModel();
            $importer = service('serviceDeskImporter');

            $done = 0;
            while (($job = $imports->nextPending()) !== null) {
                $id = (int) $job['id'];
                CLI::write(sprintf('[%s] Procesando importación #%d (%s)…', date('H:i:s'), $id, $job['source_filename'] ?? ''), 'cyan');

                try {
                    $importer->run($id);
                    $fresh = $imports->find($id);
                    CLI::write(sprintf(
                        '  #%d: %s — %s ok, %s con error.',
                        $id,
                        $fresh['status'] ?? 'ready',
                        $fresh['succeeded_rows'] ?? 0,
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
                CLI::write('No hay importaciones pendientes.', 'yellow');
            } else {
                CLI::write(sprintf('[%s] Listo. %d importación(es) procesada(s).', date('H:i:s'), $done), 'green');
            }
        } finally {
            $lock->release();
        }
    }
}
