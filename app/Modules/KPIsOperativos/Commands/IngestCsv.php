<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Commands;

use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Models\GlpiTicketModel;
use App\Modules\KPIsOperativos\Services\GlpiCsvSource;
use App\Modules\KPIsOperativos\Services\GlpiIdcHomologator;
use App\Modules\KPIsOperativos\Services\GlpiKpiCalculator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTime;

/**
 * Smoke test del parser: ingiere un CSV/XLSX directamente sin pasar por el form HTTP.
 *
 * Uso:
 *   php spark kpi:ingest-csv <ruta> [--name "Mayo 2026"] [--desde 2026-05-01] [--hasta 2026-05-31]
 */
class IngestCsv extends BaseCommand
{
    protected $group       = 'KPIsOperativos';
    protected $name        = 'kpi:ingest-csv';
    protected $description = 'Ingesta un CSV/XLSX de GLPI directamente (smoke test del parser).';
    protected $usage       = 'kpi:ingest-csv <ruta> [--name <nombre>] [--desde YYYY-MM-DD] [--hasta YYYY-MM-DD]';
    protected $arguments   = [
        'path' => 'Ruta absoluta al archivo CSV o XLSX.',
    ];
    protected $options = [
        '--name'  => 'Nombre del reporte (default: nombre del archivo).',
        '--desde' => 'Filtro fecha_apertura >= esta fecha (YYYY-MM-DD).',
        '--hasta' => 'Filtro fecha_apertura <= esta fecha (YYYY-MM-DD).',
    ];

    public function run(array $params): void
    {
        $path = $params[0] ?? null;
        if (! $path || ! is_file($path)) {
            CLI::error('Ruta inválida o archivo inexistente: ' . ($path ?? '(vacío)'));
            return;
        }

        $name  = (string) (CLI::getOption('name') ?: pathinfo($path, PATHINFO_FILENAME));
        $desde = CLI::getOption('desde');
        $hasta = CLI::getOption('hasta');

        $from = $desde ? new DateTime($desde . ' 00:00:00') : null;
        $to   = $hasta ? new DateTime($hasta . ' 23:59:59') : null;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        CLI::write("Archivo: {$path}", 'cyan');
        CLI::write("Tipo:    {$ext}", 'cyan');
        CLI::write("Nombre:  {$name}", 'cyan');

        $reports = new GlpiReportModel();
        $tickets = new GlpiTicketModel();

        $reportId = $reports->insert([
            'name'            => $name,
            'period_start'    => $desde ?: null,
            'period_end'      => $hasta ?: null,
            'source_type'     => $ext === 'csv' ? 'csv' : 'xlsx',
            'source_filename' => basename($path),
            'source_path'     => $path,
            'status'          => 'processing',
            'total_tickets'   => 0,
        ], true);

        if (! $reportId) {
            CLI::error('No se pudo crear el report en BD.');
            return;
        }

        CLI::write("Report creado id={$reportId}", 'green');

        try {
            $source = new GlpiCsvSource($path);
            $source->setDateRange($from, $to);
            $homologator = new GlpiIdcHomologator();

            $batch     = [];
            $batchSize = 500;
            $total     = 0;
            $now       = date('Y-m-d H:i:s');
            $started   = microtime(true);

            foreach ($source->tickets() as $t) {
                $fa = $t['fecha_apertura'] instanceof \DateTimeInterface ? $t['fecha_apertura']->format('Y-m-d H:i:s') : null;
                $fc = $t['fecha_cierre']   instanceof \DateTimeInterface ? $t['fecha_cierre']->format('Y-m-d H:i:s') : null;

                $horas = null;
                if ($fa && $fc) {
                    $secs = strtotime($fc) - strtotime($fa);
                    if ($secs >= 0) {
                        $horas = round($secs / 3600, 2);
                    }
                }

                $batch[] = [
                    'report_id'        => $reportId,
                    'glpi_id'          => mb_substr((string) $t['glpi_id'], 0, 40) ?: null,
                    'titulo'           => mb_substr((string) $t['titulo'], 0, 500) ?: null,
                    'estado'           => mb_substr((string) $t['estado'], 0, 60) ?: null,
                    'fecha_apertura'   => $fa,
                    'fecha_cierre'     => $fc,
                    'regional'         => mb_substr((string) $t['regional'], 0, 120) ?: null,
                    'estado_geo'       => mb_substr((string) $t['estado_geo'], 0, 120) ?: null,
                    'municipio'        => mb_substr((string) $t['municipio'], 0, 120) ?: null,
                    'proyecto'         => mb_substr((string) $t['proyecto'], 0, 160) ?: null,
                    'categoria'        => mb_substr((string) $t['categoria'], 0, 160) ?: null,
                    'solicitud'        => mb_substr((string) $t['solicitud'], 0, 120) ?: null,
                    'idc'              => mb_substr((string) $t['idc'], 0, 160) ?: null,
                    'idc_canonical_id' => trim((string) $t['idc']) !== '' ? $homologator->resolve((string) $t['idc'], (int) $reportId) : null,
                    'urgencia'         => mb_substr((string) $t['urgencia'], 0, 40) ?: null,
                    'impacto'          => mb_substr((string) $t['impacto'], 0, 40) ?: null,
                    'sucursal'         => mb_substr((string) $t['sucursal'], 0, 255) ?: null,
                    'cliente'          => mb_substr((string) $t['cliente'], 0, 160) ?: null,
                    'horas_resolucion' => $horas,
                    'created_at'       => $now,
                ];

                if (count($batch) >= $batchSize) {
                    $tickets->insertBatchSafe($batch);
                    $total += count($batch);
                    $batch = [];
                    CLI::write("  procesados: {$total}", 'dark_gray');
                }
            }

            if (! empty($batch)) {
                $tickets->insertBatchSafe($batch);
                $total += count($batch);
            }

            $elapsed = round(microtime(true) - $started, 2);

            $reports->update($reportId, ['total_tickets' => $total]);

            CLI::write("Calculando snapshot KPIs...", 'cyan');
            $kpis = (new GlpiKpiCalculator())->computeAndSave((int) $reportId);

            $reports->update($reportId, ['status' => 'ready']);

            CLI::newLine();
            CLI::write("✅ Ingesta completa: {$total} tickets en {$elapsed}s.", 'green');
            CLI::write(sprintf(
                "   total=%d, cerrados=%d, en_curso=%d, tasa=%d%%, sla=%d%%, prom_h=%.1f",
                $kpis['total'], $kpis['cerrados'], $kpis['en_curso'],
                $kpis['tasa_cierre'], $kpis['sla_pct'], $kpis['prom_h']
            ), 'green');
            CLI::write("Report id={$reportId}, status=ready.", 'green');
        } catch (\Throwable $e) {
            $reports->update($reportId, [
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            CLI::error('Fallo: ' . $e->getMessage());
            CLI::write($e->getTraceAsString(), 'dark_gray');
        }
    }
}
