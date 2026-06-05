<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Commands;

use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Services\GlpiKpiCalculator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Recalcula el snapshot kpi_json de un reporte existente.
 *
 * Uso:
 *   php spark kpi:recompute <reportId>
 *   php spark kpi:recompute --all
 *   php spark kpi:recompute <reportId> --diff <ruta-json>   # imprime diff vs un JSON de referencia
 */
class Recompute extends BaseCommand
{
    protected $group       = 'KPIsOperativos';
    protected $name        = 'kpi:recompute';
    protected $description = 'Recalcula el snapshot kpi_json de uno o todos los reportes.';
    protected $usage       = 'kpi:recompute [<reportId>|--all] [--diff <json>]';
    protected $arguments   = [
        'reportId' => 'Id del reporte a recalcular.',
    ];
    protected $options = [
        '--all'  => 'Recalcula todos los reportes en estado ready.',
        '--diff' => 'Ruta a un JSON de referencia para comparar diferencias.',
    ];

    public function run(array $params): void
    {
        $all  = CLI::getOption('all') !== null;
        $diff = CLI::getOption('diff');

        $calc    = new GlpiKpiCalculator();
        $reports = new GlpiReportModel();

        $ids = [];
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
            CLI::write('No hay reportes para recalcular.', 'yellow');
            return;
        }

        foreach ($ids as $id) {
            $report = $reports->find($id);
            if (! $report) {
                CLI::write("⚠ Reporte {$id} no encontrado, skip.", 'yellow');
                continue;
            }

            CLI::write("Recalculando reporte {$id} ({$report['name']})...", 'cyan');
            $kpis = $calc->computeAndSave($id);

            CLI::write(sprintf(
                "  total=%d, cerrados=%d, en_curso=%d, tasa=%d%%, sla=%d%%, prom_h=%.1f",
                $kpis['total'], $kpis['cerrados'], $kpis['en_curso'],
                $kpis['tasa_cierre'], $kpis['sla_pct'], $kpis['prom_h']
            ), 'green');

            if ($diff) {
                $this->printDiff($kpis, (string) $diff);
            }
        }

        CLI::write('✅ Listo.', 'green');
    }

    /**
     * Campos cuyo orden es semánticamente irrelevante (dicts/listas con
     * tie-breaks naturales): comparamos como conjuntos.
     *
     * @var list<string>
     */
    private const ORDER_INSENSITIVE = ['coord_tickets', 'coord_info', 'idc_bottom'];

    /**
     * @param array<string, mixed> $kpis
     */
    private function printDiff(array $kpis, string $refPath): void
    {
        if (! is_file($refPath)) {
            CLI::error("  ⚠ Ref no encontrado: {$refPath}");
            return;
        }

        $ref = json_decode((string) file_get_contents($refPath), true);
        if (! is_array($ref)) {
            CLI::error("  ⚠ Ref JSON inválido.");
            return;
        }

        $strictDiffs   = [];
        $semanticDiffs = [];

        foreach ($ref as $key => $refVal) {
            $myVal = $kpis[$key] ?? null;

            $orderInsensitive = in_array($key, self::ORDER_INSENSITIVE, true);

            if ($this->valuesEqual($refVal, $myVal, $orderInsensitive)) {
                continue;
            }

            $entry = ['ref' => $refVal, 'mine' => $myVal];
            if ($orderInsensitive) {
                $semanticDiffs[$key] = $entry;
            } else {
                $strictDiffs[$key] = $entry;
            }
        }

        if (empty($strictDiffs) && empty($semanticDiffs)) {
            CLI::write('  ✓ Snapshot coincide 1:1 con referencia.', 'green');
            return;
        }

        if (! empty($strictDiffs)) {
            CLI::write('  ✗ Diferencias reales (' . count($strictDiffs) . '):', 'red');
            foreach ($strictDiffs as $key => $d) {
                $this->printDiffLine($key, $d, 'red');
            }
        }

        if (! empty($semanticDiffs)) {
            CLI::write(
                '  ℹ Diferencias toleradas (' . count($semanticDiffs) . ' campo[s] con tie-breaks distintos al Python):',
                'yellow'
            );
            foreach ($semanticDiffs as $key => $d) {
                $this->printDiffLine($key, $d, 'dark_gray');
            }
        }
    }

    /**
     * @param array{ref: mixed, mine: mixed} $d
     */
    private function printDiffLine(string $key, array $d, string $color): void
    {
        $refStr  = is_scalar($d['ref'])  ? (string) $d['ref']  : json_encode($d['ref'],  JSON_UNESCAPED_UNICODE);
        $mineStr = is_scalar($d['mine']) ? (string) $d['mine'] : json_encode($d['mine'], JSON_UNESCAPED_UNICODE);
        CLI::write("    {$key}:", $color);
        CLI::write("      ref:  " . mb_substr($refStr, 0, 200), 'dark_gray');
        CLI::write("      mine: " . mb_substr($mineStr, 0, 200), 'dark_gray');
    }

    private function valuesEqual(mixed $a, mixed $b, bool $orderInsensitive): bool
    {
        if (! $orderInsensitive) {
            return json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
        }

        // Para dicts: compara claves y valores sin importar el orden
        if (is_array($a) && is_array($b)) {
            if (count($a) !== count($b)) {
                return false;
            }

            // Si parece dict (string keys o assoc)
            $isAssoc = $a !== array_values($a) || $b !== array_values($b);
            if ($isAssoc) {
                ksort($a);
                ksort($b);
                return json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
            }

            // Lista de tuplas: ordena por JSON serializado de cada elemento
            $sortedA = $a; $sortedB = $b;
            usort($sortedA, fn($x, $y) => strcmp(json_encode($x), json_encode($y)));
            usort($sortedB, fn($x, $y) => strcmp(json_encode($x), json_encode($y)));
            return json_encode($sortedA, JSON_UNESCAPED_UNICODE) === json_encode($sortedB, JSON_UNESCAPED_UNICODE);
        }

        return $a === $b;
    }
}
