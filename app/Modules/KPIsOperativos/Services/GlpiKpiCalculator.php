<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use App\Modules\KPIsOperativos\Models\GlpiCoordinatorModel;
use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Calcula el snapshot KPI de un reporte ya ingerido.
 *
 * Espejo PHP de extraer_kpis() en _reference/generar_pptx.py. La salida
 * cumple el contrato de kpi_data.example.json (21 campos top-level).
 *
 * Trabaja sobre la BD: queries con GROUP BY contra el report dado.
 */
final class GlpiKpiCalculator
{
    private BaseConnection $db;
    private string $table = 'glpi_tickets';

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * Calcula el snapshot completo y lo retorna como array.
     *
     * @return array<string, mixed>
     */
    public function compute(int $reportId): array
    {
        $total = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->countAllResults();

        if ($total === 0) {
            return $this->emptySnapshot();
        }

        $estadosTicket = $this->ranking('estado', $reportId, null);

        $cerrados = 0;
        foreach ($estadosTicket as [$label, $count]) {
            if (in_array($label, GlpiSchema::CLOSED_STATES, true)) {
                $cerrados += $count;
            }
        }
        $enCurso    = $total - $cerrados;
        $tasaCierre = $total > 0 ? (int) round($cerrados / $total * 100) : 0;

        // SLA & prom_h: solo cerrados con horas_resolucion no nula (parser ya
        // descartó casos con fc < fa o fechas inválidas)
        $sla = $this->db->query("
            SELECT
                COUNT(*)                                                AS n_valid,
                ROUND(AVG(horas_resolucion), 1)                         AS prom_h,
                ROUND(100 * SUM(CASE WHEN horas_resolucion <= 24 THEN 1 ELSE 0 END) / COUNT(*)) AS sla_pct
            FROM {$this->table}
            WHERE report_id = ?
              AND estado IN ('Cerrado', 'Resuelto')
              AND horas_resolucion IS NOT NULL
        ", [$reportId])->getRow();

        $promH  = $sla && $sla->n_valid > 0 ? (float) $sla->prom_h  : 0.0;
        $slaPct = $sla && $sla->n_valid > 0 ? (int)   $sla->sla_pct : 0;

        // Faltantes
        $sinReg = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->where('regional', null)
            ->countAllResults();

        $sinIdc = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->groupStart()
                ->where('idc', null)
                ->orWhere('idc', GlpiSchema::IDC_UNASSIGNED)
            ->groupEnd()
            ->countAllResults();

        // Rankings (excluyen NULL automáticamente con WHERE col IS NOT NULL)
        $regTop = $this->ranking('regional',   $reportId, 8);
        $estTop = $this->ranking('estado_geo', $reportId, 8);
        $catTop = $this->ranking('categoria',  $reportId, 7);
        $proyTop = $this->ranking('proyecto',  $reportId, 5);

        // IDC: excluye nulos y "SIN ASIGNAR"
        $idcAll = $this->db->query("
            SELECT idc AS label, COUNT(*) AS n
            FROM {$this->table}
            WHERE report_id = ?
              AND idc IS NOT NULL
              AND idc <> ?
            GROUP BY idc
            ORDER BY n DESC, idc ASC
        ", [$reportId, GlpiSchema::IDC_UNASSIGNED])->getResultArray();

        $idcTop = array_map(fn($r) => [$r['label'], (int) $r['n']], array_slice($idcAll, 0, 6));

        // Bottom 6: los 6 técnicos con menor carga.
        // Cuando hay decenas empatados en n=1, el orden exacto es arbitrario —
        // ordenamos por n ASC y luego label ASC para determinismo.
        $idcAsc = $idcAll;
        usort($idcAsc, function ($a, $b) {
            $diff = $a['n'] <=> $b['n'];
            return $diff !== 0 ? $diff : strcmp($a['label'], $b['label']);
        });
        $idcBottom = array_map(fn($r) => [$r['label'], (int) $r['n']], array_slice($idcAsc, 0, 6));

        // Envíos (sub-pipeline): substring "ENVI" case-insensitive
        $envSubs = GlpiSchema::ENVIOS_CATEGORY_SUBSTRING;
        $envRow = $this->db->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado IN ('Cerrado', 'Resuelto') THEN 1 ELSE 0 END) AS cerrados
            FROM {$this->table}
            WHERE report_id = ?
              AND UPPER(categoria) LIKE CONCAT('%', UPPER(?), '%')
        ", [$reportId, $envSubs])->getRow();

        $envTotal = (int) ($envRow->total    ?? 0);
        $envCerr  = (int) ($envRow->cerrados ?? 0);
        $envPend  = $envTotal - $envCerr;
        $envPct   = $envTotal > 0 ? (int) round($envCerr / $envTotal * 100) : 0;

        // Coordinación: conteo por regional + cruce con glpi_coordinators
        $coordTickets = [];
        foreach ($regTop as $row) {
            // regTop ya viene ordenado y limitado a 8 — incluyente para el dict
            $coordTickets[$row[0]] = $row[1];
        }

        // Si hay más regionales (rare beyond top 8), las agregamos:
        $regAll = $this->db->query("
            SELECT regional AS label, COUNT(*) AS n
            FROM {$this->table}
            WHERE report_id = ? AND regional IS NOT NULL
            GROUP BY regional
            ORDER BY n DESC, regional ASC
        ", [$reportId])->getResultArray();
        foreach ($regAll as $r) {
            $coordTickets[$r['label']] = (int) $r['n'];
        }

        $coordMap  = (new GlpiCoordinatorModel())->getNormalizedMap();
        $coordInfo = [];
        foreach ($coordTickets as $zone => $_count) {
            $key = GlpiCoordinatorModel::normalizeZone($zone);
            if (isset($coordMap[$key])) {
                $coordInfo[$zone] = [
                    'coord' => $coordMap[$key]['coord'],
                    'gte'   => $coordMap[$key]['gte'],
                ];
            } else {
                $coordInfo[$zone] = ['coord' => $zone, 'gte' => '—'];
            }
        }

        return [
            'total'          => $total,
            'cerrados'       => $cerrados,
            'en_curso'       => $enCurso,
            'tasa_cierre'    => $tasaCierre,
            'sla_pct'        => $slaPct,
            'prom_h'         => $promH,
            'sin_reg'        => $sinReg,
            'sin_idc'        => $sinIdc,
            'reg_top'        => $regTop,
            'est_top'        => $estTop,
            'idc_top'        => $idcTop,
            'idc_bottom'     => $idcBottom,
            'cat_top'        => $catTop,
            'proy_top'       => $proyTop,
            'estados_ticket' => $estadosTicket,
            'env_total'      => $envTotal,
            'env_cerr'       => $envCerr,
            'env_pend'       => $envPend,
            'env_pct'        => $envPct,
            'coord_tickets'  => $coordTickets,
            'coord_info'     => $coordInfo,
        ];
    }

    /**
     * Calcula el snapshot y lo persiste en glpi_reports.kpi_json.
     *
     * @return array<string, mixed>
     */
    public function computeAndSave(int $reportId): array
    {
        $kpis = $this->compute($reportId);

        $ok = (new GlpiReportModel())->update($reportId, [
            'kpi_json' => json_encode($kpis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if (! $ok) {
            throw new RuntimeException("No se pudo guardar el snapshot KPI del reporte {$reportId}.");
        }

        return $kpis;
    }

    /**
     * Ranking por una columna; excluye NULL. Si $limit es null, devuelve todo.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function ranking(string $column, int $reportId, ?int $limit): array
    {
        $sql = "SELECT {$column} AS label, COUNT(*) AS n
                FROM {$this->table}
                WHERE report_id = ?
                  AND {$column} IS NOT NULL
                GROUP BY {$column}
                ORDER BY n DESC, {$column} ASC";

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        $rows = $this->db->query($sql, [$reportId])->getResultArray();
        return array_map(static fn($r) => [(string) $r['label'], (int) $r['n']], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySnapshot(): array
    {
        return [
            'total' => 0, 'cerrados' => 0, 'en_curso' => 0, 'tasa_cierre' => 0,
            'sla_pct' => 0, 'prom_h' => 0.0,
            'sin_reg' => 0, 'sin_idc' => 0,
            'reg_top' => [], 'est_top' => [], 'idc_top' => [], 'idc_bottom' => [],
            'cat_top' => [], 'proy_top' => [], 'estados_ticket' => [],
            'env_total' => 0, 'env_cerr' => 0, 'env_pend' => 0, 'env_pct' => 0,
            'coord_tickets' => new \stdClass(),
            'coord_info'    => new \stdClass(),
        ];
    }
}
