<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use App\Modules\KPIsOperativos\Models\GlpiCoordinatorModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Variante de GlpiKpiCalculator que computa el snapshot KPI restringido a
 * una de las áreas del árbol de decisión (admin / ops_lab / ops_other).
 *
 * No persiste nada en kpi_json — su salida alimenta sólo al
 * GlpiAreaPptxRenderer, que es lo único nuevo en este flujo. El dashboard
 * y el PPTX existentes quedan intactos.
 */
final class GlpiAreaKpiCalculator
{
    private BaseConnection $db;
    private string $table = 'kpi_glpi_tickets';

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * Computa el snapshot para una sola área. Forma idéntica a
     * GlpiKpiCalculator::compute() — los renderers de slides la consumen
     * sin distinguir si es global o por área.
     *
     * @return array<string, mixed>
     */
    public function compute(int $reportId, string $area): array
    {
        $areaSql = GlpiSchema::areaSqlCondition($area);

        $total = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->where($areaSql, null, false)
            ->countAllResults();

        if ($total === 0) {
            return $this->emptySnapshot();
        }

        $estadosTicket = $this->ranking('estado', $reportId, $areaSql, null);

        $cerrados = 0;
        foreach ($estadosTicket as [$label, $count]) {
            if (in_array($label, GlpiSchema::CLOSED_STATES, true)) {
                $cerrados += $count;
            }
        }
        $enCurso    = $total - $cerrados;
        $tasaCierre = $total > 0 ? round($cerrados / $total * 100, 2) : 0.0;

        $sla = $this->db->query("
            SELECT
                COUNT(*)                                                AS n_valid,
                ROUND(AVG(horas_resolucion), 1)                         AS prom_h,
                ROUND(100 * SUM(CASE WHEN horas_resolucion <= 24 THEN 1 ELSE 0 END) / COUNT(*), 2) AS sla_pct
            FROM {$this->table}
            WHERE report_id = ?
              AND estado IN ('Cerrado', 'Resuelto')
              AND horas_resolucion IS NOT NULL
              AND {$areaSql}
        ", [$reportId])->getRow();

        $promH  = $sla && $sla->n_valid > 0 ? (float) $sla->prom_h  : 0.0;
        $slaPct = $sla && $sla->n_valid > 0 ? (float) $sla->sla_pct : 0.0;

        // Reglas de exclusión por categoría:
        //   • Regional: excluye envíos, almacén y laboratorio.
        //   • IDC: excluye envíos y almacén.
        $notEnvios   = GlpiSchema::notEnviosSqlCondition();
        $regAppliesC = GlpiSchema::notRegionalApplicableSqlCondition();

        $sinReg = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->where('regional', null)
            ->where($areaSql, null, false)
            ->where($regAppliesC, null, false)
            ->countAllResults();

        $sinIdc = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->where($areaSql, null, false)
            ->where($notEnvios, null, false)
            ->groupStart()
                ->where('idc', null)
                ->orWhere('idc', GlpiSchema::IDC_UNASSIGNED)
            ->groupEnd()
            ->countAllResults();

        // Denominador para porcentajes de cobertura regional dentro del área.
        $regUniverse = (int) $this->db->table($this->table)
            ->where('report_id', $reportId)
            ->where($areaSql, null, false)
            ->where($regAppliesC, null, false)
            ->countAllResults();

        // Rankings: regional usa la condición específica; el resto usa
        // el universo del área.
        $regTop  = $this->ranking('regional',   $reportId, $areaSql, 8, regionalScope: true);
        $estTop  = $this->ranking('estado_geo', $reportId, $areaSql, 8);
        $catTop  = $this->ranking('categoria',  $reportId, $areaSql, 7);
        $proyTop = $this->ranking('proyecto',   $reportId, $areaSql, 5);

        // IDC: agrupado por canonical_name con fallback al raw `idc`.
        // Excluye envíos y almacén.
        $idcAll = $this->db->query("
            SELECT
                COALESCE(c.canonical_name, t.idc) AS label,
                COUNT(*)                          AS n
            FROM {$this->table} t
            LEFT JOIN kpi_glpi_idc_canonical c ON c.id = t.idc_canonical_id
            WHERE t.report_id = ?
              AND t.idc IS NOT NULL
              AND t.idc <> ?
              AND {$areaSql}
              AND " . GlpiSchema::notEnviosSqlCondition('t.categoria') . "
            GROUP BY label
            ORDER BY n DESC, label ASC
        ", [$reportId, GlpiSchema::IDC_UNASSIGNED])->getResultArray();

        $idcTop = array_map(fn($r) => [$r['label'], (int) $r['n']], array_slice($idcAll, 0, 10));

        $idcAsc = $idcAll;
        usort($idcAsc, function ($a, $b) {
            $diff = $a['n'] <=> $b['n'];
            return $diff !== 0 ? $diff : strcmp($a['label'], $b['label']);
        });
        $idcBottom = array_map(fn($r) => [$r['label'], (int) $r['n']], array_slice($idcAsc, 0, 10));

        // Envíos sólo tiene sentido dentro de Admin (es la sub-categoría
        // ‘Control de Envíos’). Para Ops devolvemos cero para que la slide
        // correspondiente no muestre números falsos.
        if ($area === GlpiSchema::AREA_ADMIN) {
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
        } else {
            $envTotal = 0;
            $envCerr  = 0;
        }
        $envPend = $envTotal - $envCerr;
        $envPct  = $envTotal > 0 ? round($envCerr / $envTotal * 100, 2) : 0.0;

        // Coordinación: reusamos la misma lógica del calculator global,
        // pero restringida al área.
        $coordTickets = [];
        foreach ($regTop as $row) {
            $coordTickets[$row[0]] = $row[1];
        }

        $regAll = $this->db->query("
            SELECT regional AS label, COUNT(*) AS n
            FROM {$this->table}
            WHERE report_id = ? AND regional IS NOT NULL AND {$areaSql}
              AND " . GlpiSchema::notRegionalApplicableSqlCondition() . "
            GROUP BY regional
            ORDER BY n DESC, regional ASC
        ", [$reportId])->getResultArray();
        foreach ($regAll as $r) {
            $coordTickets[$r['label']] = (int) $r['n'];
        }

        $coordMap  = (new GlpiCoordinatorModel())->getNormalizedMap();
        $coordInfo = [];
        foreach ($coordTickets as $zone => $_count) {
            $key = GlpiCoordinatorModel::normalizeZone((string) $zone);
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
            'reg_universe'   => $regUniverse,
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
     * Computa los snapshots de las 3 áreas en paralelo. Devuelve un mapa
     * `area_key => snapshot` listo para alimentar al PPTX renderer.
     *
     * @return array<string, array<string, mixed>>
     */
    public function computeAll(int $reportId): array
    {
        return [
            GlpiSchema::AREA_ADMIN     => $this->compute($reportId, GlpiSchema::AREA_ADMIN),
            GlpiSchema::AREA_OPS_LAB   => $this->compute($reportId, GlpiSchema::AREA_OPS_LAB),
            GlpiSchema::AREA_OPS_OTHER => $this->compute($reportId, GlpiSchema::AREA_OPS_OTHER),
        ];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function ranking(
        string $column,
        int $reportId,
        string $areaSql,
        ?int $limit,
        bool $regionalScope = false
    ): array {
        $extraFilter = $regionalScope
            ? ' AND ' . GlpiSchema::notRegionalApplicableSqlCondition()
            : '';

        $sql = "SELECT {$column} AS label, COUNT(*) AS n
                FROM {$this->table}
                WHERE report_id = ?
                  AND {$column} IS NOT NULL
                  AND {$areaSql}
                  {$extraFilter}
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
            'total' => 0, 'cerrados' => 0, 'en_curso' => 0, 'tasa_cierre' => 0.0,
            'sla_pct' => 0.0, 'prom_h' => 0.0,
            'sin_reg' => 0, 'sin_idc' => 0, 'reg_universe' => 0,
            'reg_top' => [], 'est_top' => [], 'idc_top' => [], 'idc_bottom' => [],
            'cat_top' => [], 'proy_top' => [], 'estados_ticket' => [],
            'env_total' => 0, 'env_cerr' => 0, 'env_pend' => 0, 'env_pct' => 0.0,
            'coord_tickets' => [],
            'coord_info'    => [],
        ];
    }
}
