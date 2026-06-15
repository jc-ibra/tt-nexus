<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ProcessRetries extends BaseCommand
{
    protected $group       = 'Provisioning';
    protected $name        = 'provisioning:process-retries';
    protected $description = 'Process pending retries in the provisioning retry queue.';
    protected $usage       = 'provisioning:process-retries [--limit <n>]';
    protected $options     = [
        '--limit' => 'Maximum number of pending retries to process (default: 50).',
    ];

    public function run(array $params): void
    {
        $limit = (int) CLI::getOption('limit') ?: 50;
        CLI::write(sprintf('[%s] Procesando hasta %d reintentos…', date('H:i:s'), $limit), 'cyan');

        $stats = service('provisioningOrchestrator')->processDueRetries($limit);

        CLI::write(sprintf(
            '[%s] Reintentos: %d procesados · %d éxito · %d fallidos · %d abandonados',
            date('H:i:s'),
            $stats['processed'],
            $stats['success'],
            $stats['failed'],
            $stats['abandoned'],
        ), $stats['failed'] > 0 || $stats['abandoned'] > 0 ? 'yellow' : 'green');
    }
}
