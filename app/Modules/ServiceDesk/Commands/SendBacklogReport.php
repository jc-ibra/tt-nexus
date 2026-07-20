<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sends the daily GLPI backlog report by email. Run every few minutes via cron;
 * the service self-gates on the configured cut-off hour and a per-day guard, so
 * it only actually sends once per day when the hour is reached.
 *
 *   php spark servicedesk:send-backlog-report            # scheduled (gated)
 *   php spark servicedesk:send-backlog-report --force     # send now, ignore gate
 *   * / 5 * * * * cd /path && php spark servicedesk:send-backlog-report >> log 2>&1
 */
class SendBacklogReport extends BaseCommand
{
    protected $group       = 'ServiceDesk';
    protected $name        = 'servicedesk:send-backlog-report';
    protected $description = 'Envía el reporte diario de backlog de GLPI por correo (auto-gatillado por hora de corte).';
    protected $usage       = 'servicedesk:send-backlog-report [--force] [--dry-run]';
    protected $options     = [
        '--force'   => 'Envía de inmediato ignorando la hora de corte y el candado diario (envío manual).',
        '--dry-run' => 'Genera el reporte y muestra los KPIs sin enviar ni registrar el correo.',
    ];

    public function run(array $params): void
    {
        $service = service('backlogReportService');
        $force   = array_key_exists('force', $params) || CLI::getOption('force');
        $dryRun  = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');

        if ($dryRun) {
            if (! $service->isConfigured()) {
                CLI::write('  La conexión a GLPI no está configurada.', 'red');
                return;
            }
            $d = $service->build();
            CLI::write(sprintf('[%s] Dry-run — corte %s', date('H:i:s'), $d['cutoffLabel']), 'cyan');
            CLI::write(sprintf('  Total abiertos: %d · Críticos +%dD: %d · En espera: %d · Sin IDC: %s%s',
                $d['total'], $d['criticalDays'], $d['critical'], $d['pending'],
                $d['idcConfigured'] ? (string) $d['sinIdc'] : 'n/a',
                $d['idcConfigured'] ? ' (de ' . $d['idcScopeTotal'] . ' en alcance IDC)' : ''), 'green');
            foreach ($d['areaOrder'] as $k) {
                $a = $d['areas'][$k];
                if (($a['total'] ?? 0) > 0) {
                    CLI::write(sprintf('  · %s: %d abiertos · %d críticos · %d en espera · %s sin IDC',
                        $a['label'], $a['total'], $a['critical'], $a['pending'],
                        $d['idcConfigured'] ? (string) $a['sinIdc'] : 'n/a'));
                }
            }
            if (! empty($d['daily'])) {
                CLI::write(sprintf('  Actividad del día (%s):', $d['dailyLabel']), 'cyan');
                foreach ($d['daily'] as $dk => $day) {
                    if ($dk === 'sin_clasificar' && ((int) $day['created'] + (int) $day['closed']) === 0) {
                        continue;
                    }
                    $net = (int) $day['net'];
                    CLI::write(sprintf('    - %s: %d generados · %d cerrados · neto %s%d',
                        $day['label'], $day['created'], $day['closed'], $net > 0 ? '+' : '', $net));
                }
            }
            if (! empty($d['regionalConfigured'])) {
                CLI::write(sprintf('  Regionales: %d', count($d['regionals'])));
                foreach (array_slice($d['regionals'], 0, 8) as $reg) {
                    CLI::write(sprintf('    - %s: %d tickets · %sd avg · %d sin IDC',
                        $reg['name'], $reg['tickets'], $reg['avgDays'], $reg['sinIdc']));
                }
            }
            if (! empty($d['clients'])) {
                CLI::write(sprintf('  Clientes: %d', count($d['clients'])));
                foreach (array_slice($d['clients'], 0, 8) as $cli) {
                    CLI::write(sprintf('    - %s: %d total · %sd avg · %dd máx · %d sin IDC',
                        $cli['name'], $cli['total'], $cli['avgDays'], $cli['maxDays'], $cli['sinIdc']));
                }
            }
            // Exercise the render path so the dry-run also catches template errors.
            $html = $service->renderHtml($d);
            CLI::write(sprintf('  HTML OK (%d bytes). Adjunto: %d filas.', strlen($html), count($d['rows'])), 'green');
            return;
        }

        if ($force) {
            CLI::write('[' . date('H:i:s') . '] Envío manual (--force)…', 'cyan');
            $result = $service->send('manual', null);
        } else {
            $result = $service->runScheduled();
        }

        if ($result->success) {
            CLI::write('  ' . $result->message, 'green');
        } else {
            // A "not the hour yet / already sent" outcome is normal for a cron tick.
            CLI::write('  ' . $result->message, 'yellow');
        }
    }
}
