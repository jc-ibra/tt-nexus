<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Commands;

use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Services\GlpiPptxRenderer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Genera el .pptx de un reporte desde CLI.
 *
 * Uso:
 *   php spark kpi:render-pptx <reportId>
 */
class RenderPptx extends BaseCommand
{
    protected $group       = 'KPIsOperativos';
    protected $name        = 'kpi:render-pptx';
    protected $description = 'Genera el .pptx de un reporte usando el snapshot KPI ya calculado.';
    protected $usage       = 'kpi:render-pptx <reportId>';
    protected $arguments   = [
        'reportId' => 'Id del reporte.',
    ];

    public function run(array $params): void
    {
        $id = (int) ($params[0] ?? 0);
        if ($id <= 0) {
            CLI::error('Indica un reportId válido.');
            return;
        }

        $reports = new GlpiReportModel();
        $report  = $reports->find($id);

        if (! $report) {
            CLI::error("Reporte {$id} no encontrado.");
            return;
        }

        if (empty($report['kpi_json'])) {
            CLI::error("El reporte {$id} no tiene snapshot KPI. Corre 'php spark kpi:recompute {$id}' primero.");
            return;
        }

        $kpi = json_decode($report['kpi_json'], true);
        if (! is_array($kpi)) {
            CLI::error('Snapshot KPI inválido.');
            return;
        }

        CLI::write("Generando PPTX para reporte {$id} ({$report['name']})...", 'cyan');
        $started = microtime(true);

        try {
            $path = (new GlpiPptxRenderer())->render($id, $kpi, (string) $report['name']);
        } catch (\Throwable $e) {
            CLI::error('Fallo: ' . $e->getMessage());
            CLI::write($e->getTraceAsString(), 'dark_gray');
            return;
        }

        $elapsed = round(microtime(true) - $started, 2);
        $size    = is_file($path) ? round(filesize($path) / 1024, 1) : 0;

        CLI::write("✅ PPTX generado en {$elapsed}s.", 'green');
        CLI::write("   Ruta:    {$path}",   'green');
        CLI::write("   Tamaño:  {$size} KB", 'green');
    }
}
