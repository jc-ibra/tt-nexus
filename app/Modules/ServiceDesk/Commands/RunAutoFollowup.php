<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Commands;

use App\Modules\Core\Services\RunLock;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Runs one auto-seguimiento pass: follow-up email + GLPI note on eligible open
 * tickets (see AutoFollowupService). Meant to run every few minutes via cron;
 * the service self-gates on being enabled and on each ticket's cooldown, so a
 * frequent tick is safe.
 *
 *   php spark servicedesk:run-autofollowup             # normal run
 *   php spark servicedesk:run-autofollowup --dry-run    # preview, no writes/sends
 *   * / 5 * * * * cd /path && php spark servicedesk:run-autofollowup >> log 2>&1
 */
class RunAutoFollowup extends BaseCommand
{
    protected $group       = 'ServiceDesk';
    protected $name        = 'servicedesk:run-autofollowup';
    protected $description = 'Envía seguimiento (correo + nota GLPI) a tickets abiertos en categorías habilitadas.';
    protected $usage       = 'servicedesk:run-autofollowup [--dry-run]';
    protected $options     = [
        '--dry-run' => 'Muestra cuántos tickets calificarían, sin enviar correos ni escribir en GLPI.',
    ];

    public function run(array $params): void
    {
        $dryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');

        $lock = RunLock::acquire('servicedesk_autofollowup', 1800);
        if ($lock === null) {
            CLI::write('  Ya hay una corrida de auto-seguimiento en curso.', 'yellow');
            return;
        }

        try {
            $result = service('serviceDeskAutoFollowup')->runScheduled($dryRun);
            CLI::write('[' . date('H:i:s') . '] ' . $result->message, $result->success ? 'green' : 'yellow');
        } finally {
            $lock->release();
        }
    }
}
