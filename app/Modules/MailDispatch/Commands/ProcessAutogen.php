<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Commands;

use App\Modules\Core\Services\RunLock;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Procesa las conversaciones autogeneradas pendientes: crea el ticket GLPI y
 * responde por el proveedor activo. Se corre por cron cada 1-2 minutos (después
 * del sync). Un lock evita corridas solapadas.
 *
 *   php spark maildispatch:process-autogen
 *   php spark maildispatch:process-autogen --batch=10 --debug
 */
class ProcessAutogen extends BaseCommand
{
    protected $group       = 'MailDispatch';
    protected $name        = 'maildispatch:process-autogen';
    protected $description = 'Crea los tickets GLPI de las conversaciones autogeneradas pendientes y responde.';
    protected $usage       = 'maildispatch:process-autogen [--batch=N] [--debug]';
    protected $options     = [
        '--batch' => 'Máximo de conversaciones a procesar en esta corrida (default 20).',
        '--debug' => 'Muestra el progreso por conversación.',
    ];

    /** Una corrida más larga que esto se asume muerta y su lock se recupera. */
    private const LOCK_STALE_SECONDS = 1800;

    public function run(array $params): void
    {
        if (! service('mailDispatchSettings')->autogestionEnabled()) {
            CLI::write('Autogestión deshabilitada. Nada que procesar.', 'yellow');
            return;
        }

        $batch = (int) ($params['batch'] ?? CLI::getOption('batch') ?? 20);
        $batch = $batch > 0 ? min($batch, 200) : 20;
        $debug = array_key_exists('debug', $params) || CLI::getOption('debug');

        $lock = RunLock::acquire('maildispatch_autogen', self::LOCK_STALE_SECONDS);
        if ($lock === null) {
            CLI::write('Ya hay un procesamiento en curso (lock presente). Saliendo.', 'yellow');
            return;
        }

        try {
            $log = $debug
                ? static fn(string $s) => CLI::write('  ' . $s, 'dark_gray')
                : static fn(string $s) => null;

            CLI::write(sprintf('[%s] Procesando autogestión (batch %d)…', date('H:i:s'), $batch), 'cyan');
            $stats = service('mailDispatchAutogen')->processPending($batch, $log);

            CLI::write(sprintf(
                '[%s] Listo: %d procesadas · %d creadas · %d en error.',
                date('H:i:s'),
                $stats['processed'],
                $stats['created'],
                $stats['failed']
            ), 'green');
        } finally {
            $lock->release();
        }
    }
}
