<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Commands;

use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Services\GlpiIdcHomologator;
use App\Modules\KPIsOperativos\Services\GlpiKpiCalculator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Recorre los tickets de un reporte (o todos), corre el homologator
 * sobre el campo idc y popula idc_canonical_id. También recalcula
 * el snapshot KPI al terminar.
 *
 * Uso:
 *   php spark kpi:rehomologate <reportId>
 *   php spark kpi:rehomologate --all
 */
class Rehomologate extends BaseCommand
{
    protected $group       = 'KPIsOperativos';
    protected $name        = 'kpi:rehomologate';
    protected $description = 'Reconstruye el catálogo canónico de IDCs sobre tickets ya ingresados.';
    protected $usage       = 'kpi:rehomologate [<reportId>|--all]';
    protected $arguments   = [
        'reportId' => 'Id del reporte. Omite con --all.',
    ];
    protected $options = [
        '--all' => 'Procesa todos los reportes en estado ready.',
    ];

    public function run(array $params): void
    {
        $all = CLI::getOption('all') !== null;

        $reports = new GlpiReportModel();
        $ids     = [];

        if ($all) {
            $rows = $reports->where('status', 'ready')->findAll();
            $ids  = array_map(static fn($r) => (int) $r['id'], $rows);
        } elseif (! empty($params[0])) {
            $ids = [(int) $params[0]];
        } else {
            CLI::error('Indica un reportId o usa --all.');
            return;
        }

        if (empty($ids)) {
            CLI::write('No hay reportes para procesar.', 'yellow');
            return;
        }

        $homologator = new GlpiIdcHomologator();
        $calc        = new GlpiKpiCalculator();
        $db          = db_connect();

        foreach ($ids as $reportId) {
            $report = $reports->find($reportId);
            if (! $report) {
                CLI::write("⚠ Reporte {$reportId} no encontrado, skip.", 'yellow');
                continue;
            }

            CLI::write("Reporte {$reportId} ({$report['name']})...", 'cyan');

            // Limpiar canonical_id previo en este reporte (no borra canonicals globales)
            $db->table('kpi_glpi_tickets')
                ->where('report_id', $reportId)
                ->update(['idc_canonical_id' => null]);

            // Iterar IDCs únicos de este reporte y mapearlos
            $uniques = $db->table('kpi_glpi_tickets')
                ->select('idc')
                ->distinct()
                ->where('report_id', $reportId)
                ->where('idc IS NOT NULL', null, false)
                ->where("idc <> ''", null, false)
                ->get()
                ->getResultArray();

            $mapping = []; // raw → canonical_id
            $started = microtime(true);

            foreach ($uniques as $row) {
                $raw = (string) $row['idc'];
                $canonicalId = $homologator->resolve($raw, $reportId);
                if ($canonicalId !== null) {
                    $mapping[$raw] = $canonicalId;
                }
            }

            // Aplicar mapping con UPDATE batch (1 query por raw → cubre miles de tickets)
            foreach ($mapping as $raw => $canonicalId) {
                $db->table('kpi_glpi_tickets')
                    ->where('report_id', $reportId)
                    ->where('idc', $raw)
                    ->update(['idc_canonical_id' => $canonicalId]);
            }

            $elapsed = round(microtime(true) - $started, 2);

            CLI::write(sprintf(
                "  %d IDCs únicos mapeados en %.2fs",
                count($mapping), $elapsed
            ), 'green');

            // Recalcular snapshot KPI
            $calc->computeAndSave($reportId);
            CLI::write("  ✓ Snapshot KPI recalculado", 'green');
        }

        $canonCount = (int) $db->table('kpi_glpi_idc_canonical')->countAllResults();
        $aliasCount = (int) $db->table('kpi_glpi_idc_aliases')->countAllResults();
        $reviewCount = (int) $db->table('kpi_glpi_idc_aliases')->where('needs_review', 1)->countAllResults();

        CLI::newLine();
        CLI::write("✅ Listo.", 'green');
        CLI::write("   Canonicals totales: {$canonCount}", 'green');
        CLI::write("   Aliases:            {$aliasCount}", 'green');
        if ($reviewCount > 0) {
            CLI::write("   ⚠ Necesitan revisión: {$reviewCount}", 'yellow');
        }
    }
}
