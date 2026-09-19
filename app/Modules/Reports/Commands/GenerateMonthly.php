<?php

declare(strict_types=1);

namespace App\Modules\Reports\Commands;

use App\Modules\Core\Services\RunLock;
use App\Modules\Reports\Config\Reports as ReportsConfig;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Freezes the monthly executive report. Run via cron on the 1st of each
 * month for the month that just closed; --period lets an operator (re)build
 * an arbitrary month by hand.
 *
 *   php spark reports:generate-monthly
 *   php spark reports:generate-monthly --period=2026-08
 *   php spark reports:generate-monthly --period=2026-08 --force
 *
 *   0 6 1 * * cd /path && php spark reports:generate-monthly >> /dev/null 2>&1
 */
class GenerateMonthly extends BaseCommand
{
    protected $group       = 'Reports';
    protected $name        = 'reports:generate-monthly';
    protected $description = 'Genera (congela) el informe ejecutivo mensual.';
    protected $usage       = 'reports:generate-monthly [--period YYYY-MM] [--force]';
    protected $options     = [
        '--period' => 'Mes a generar (YYYY-MM). Default: el mes que acaba de cerrar.',
        '--force'  => 'Regenera un mes que ya esté listo (conserva la versión anterior).',
    ];

    public function run(array $params): void
    {
        $config = config(ReportsConfig::class);

        $period = CLI::getOption('period');
        if ($period) {
            if (! preg_match('/^(\d{4})-(\d{2})$/', (string) $period, $m)) {
                CLI::error('Formato de --period inválido, use YYYY-MM.');
                return;
            }
            [$year, $month] = [(int) $m[1], (int) $m[2]];
        } else {
            $ts    = strtotime('first day of last month');
            $year  = (int) date('Y', $ts);
            $month = (int) date('n', $ts);
        }

        $force = (bool) CLI::getOption('force');

        $lock = RunLock::acquire($config->lockName, $config->lockStaleSeconds);
        if ($lock === null) {
            CLI::write('Ya hay una corrida en curso (lock presente). Saliendo.', 'yellow');
            return;
        }

        try {
            CLI::write(sprintf('Generando informe %04d-%02d%s...', $year, $month, $force ? ' (force)' : ''), 'cyan');
            $row = service('reportSnapshotBuilder')->generate($year, $month, null, $force);
            CLI::write(sprintf('Listo: status=%s, total_tickets=%d.', $row['status'], $row['total_tickets']), 'green');
        } catch (\Throwable $e) {
            CLI::error('Error generando el informe: ' . $e->getMessage());
        } finally {
            $lock->release();
        }
    }
}
