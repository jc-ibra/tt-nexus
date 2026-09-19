<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Providers;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use App\Modules\KPIsOperativos\Services\KpisOperativosBridge;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\Reports\Services\ReportPeriod;
use App\Modules\Reports\Services\ReportsSettingsService;
use App\Modules\ServiceDesk\Services\GlpiSchemaIntrospector;
use App\Modules\ServiceDesk\Services\GlpiValueResolver;
use CodeIgniter\Database\BaseConnection;

/**
 * Ticket-level KPIs straight from GLPI, replacing the legacy CSV upload
 * (KPIsOperativos). Reproduces most of the 22-key contract from
 * GlpiKpiCalculator::compute() (see docs/modulos/reports/spec.md), minus
 * `proyecto`/`cliente` — dropped on purpose (see LOGICAL_FIELDS below) — and
 * `idc` renamed to `ids` to match the current GLPI terminology.
 *
 * "Regional / Estado / Municipio / Sucursal" and "IDS" (the technician
 * identity) each live in a plugin Additional Fields container, and — unlike
 * the legacy CSV, which had them all in one flat export — NOT necessarily
 * the same one (in this deployment: "Clientes Externos" for the first four,
 * a dedicated "IDS" container for the technician). reports_settings.
 * glpi_field_bindings maps each logical key to its own {container_id, field}
 * pair, resolved independently. A key with no working binding degrades that
 * one breakdown instead of failing the whole snapshot.
 */
class GlpiTicketsProvider implements ReportSectionProvider
{
    /** GLPI ticket.status codes considered closed (Resuelto, Cerrado). */
    private const CLOSED_STATUS_CODES = [5, 6];

    private const STATUS_LABELS = [
        1 => 'Nuevo',
        2 => 'En curso (asignada)',
        3 => 'En curso (planificada)',
        4 => 'En espera',
        5 => 'Resuelto',
        6 => 'Cerrado',
    ];

    /**
     * Logical fields resolved from plugin containers. `proyecto` and
     * `cliente` are deliberately absent: that information is already carried
     * by the GLPI category hierarchy (e.g. "OP > CE > Afirme > Edificios"),
     * and the one plugin field literally named "Proyecto" lives inside the
     * Control de Envíos container — it is shipment metadata, not a
     * general per-ticket project.
     */
    private const LOGICAL_FIELDS = ['regional', 'estado_geo', 'municipio', 'sucursal', 'ids'];

    public function __construct(
        private GlpiDbConnection $glpi,
        private GlpiSchemaIntrospector $introspector,
        private GlpiValueResolver $values,
        private KpisOperativosBridge $kpisBridge,
        private ReportsSettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'glpi_tickets';
    }

    public function label(): string
    {
        return 'Mesa de Ayuda - GLPI';
    }

    public function isAvailable(): bool
    {
        return $this->glpi->isConfigured();
    }

    /**
     * A `reports_snapshots.payload_json` is frozen once generated — a month
     * from before a given field/shape existed keeps missing it forever
     * unless someone regenerates it. Every consumer of the `glpi_tickets`
     * section (dashboard, presentation, PPTX, XLSX) runs the decoded array
     * through this FIRST, so an old frozen month keeps rendering — with
     * sane empty defaults for whatever it predates — instead of crashing on
     * an undefined array key every time this class grows a new field.
     *
     * @param array<string,mixed> $glpi decoded payload_json['glpi_tickets'], already known 'available'
     * @return array<string,mixed>
     */
    public static function withDefaults(array $glpi): array
    {
        $defaults = [
            'fields_resolved'        => array_fill_keys(self::LOGICAL_FIELDS, false),
            'client_data_available'  => false,
            'ids_available'          => false,
            'total' => 0, 'cerrados' => 0, 'en_curso' => 0, 'tasa_cierre' => 0.0,
            'sla_pct' => 0.0, 'prom_h' => 0.0,
            'sin_reg' => 0, 'sin_ids' => 0, 'reg_universe' => 0,
            'reg_top' => [], 'est_top' => [], 'est_bottom' => [], 'ids_top' => [], 'ids_bottom' => [],
            'cat_top' => [], 'cat_leaf_total' => 0, 'estados_ticket' => [],
            'env_total' => 0, 'env_cerr' => 0, 'env_pend' => 0, 'env_pct' => 0.0,
            'coord_tickets' => [], 'coord_info' => [],
        ];

        $merged = $glpi + $defaults;
        $merged['cat_top'] = self::normalizeCategoryRows($merged['cat_top']);
        return $merged;
    }

    /**
     * A month frozen before `cat_top` started carrying {label,value,tier}
     * rows had a flat [label, count] tuple instead — normalized here so
     * withDefaults() above never hands a consumer a mixed-shape list.
     *
     * @param array<int,mixed> $catTop raw rows from payload_json.glpi_tickets.cat_top
     * @return list<array{label:string,value:int,tier:string}>
     */
    private static function normalizeCategoryRows(array $catTop): array
    {
        $out = [];
        foreach ($catTop as $row) {
            if (is_array($row) && array_key_exists('tier', $row)) {
                $out[] = $row;
                continue;
            }
            // Legacy shape: a plain [label, count] tuple, no grouping at all.
            $out[] = [
                'label' => (string) ($row[0] ?? $row['label'] ?? ''),
                'value' => (int) ($row[1] ?? $row['value'] ?? 0),
                'tier'  => 'single',
            ];
        }
        return $out;
    }

    public function collect(ReportPeriod $period): array
    {
        $db = $this->glpi->connection();

        $rows = $db->table('glpi_tickets')
            ->select('id, status, date, closedate, solvedate, itilcategories_id')
            ->where('is_deleted', 0)
            ->where('date >=', $period->start)
            ->where('date <=', $period->end)
            ->get()->getResultArray();

        if ($rows === []) {
            return $this->emptySnapshot();
        }

        $categoryNames        = $this->categoryNamesFor($db, $rows);
        $categoryGroups       = $this->categoryGroupLabelsFor($db, $rows);
        [$fieldData, $resolved] = $this->fieldDataFor($db, array_column($rows, 'id'));
        $slaHours              = $this->settings->slaHours();

        // Flatten each ticket into the same shape the legacy kpi_glpi_tickets
        // row had, so the aggregation below mirrors GlpiKpiCalculator.
        $tickets = [];
        foreach ($rows as $r) {
            $id         = (int) $r['id'];
            $statusCode = (int) $r['status'];
            $closed     = in_array($statusCode, self::CLOSED_STATUS_CODES, true);
            $closeDate  = $r['closedate'] ?: $r['solvedate'];
            $horas      = null;
            if ($closed && $closeDate) {
                $diffMin = (strtotime($closeDate) - strtotime($r['date'])) / 60;
                if ($diffMin >= 0) {
                    $horas = round($diffMin / 60, 2);
                }
            }

            $tickets[] = [
                'estado'           => self::STATUS_LABELS[$statusCode] ?? "Estado {$statusCode}",
                'categoria'        => $categoryNames[(int) $r['itilcategories_id']] ?? null,
                'categoria_group'  => $categoryGroups[(int) $r['itilcategories_id']] ?? null,
                'horas_resolucion' => $horas,
                'closed'           => $closed,
                ...($fieldData[$id] ?? array_fill_keys(self::LOGICAL_FIELDS, null)),
            ];
        }

        return $this->aggregate($tickets, $slaHours, $resolved);
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    /** @return array<int,string> itilcategories_id => full name */
    private function categoryNamesFor(BaseConnection $db, array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'itilcategories_id'))));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = $this->values->categoryName((int) $id);
        }
        return $out;
    }

    /**
     * Groups a leaf category with its immediate parent ONLY when that parent
     * is a "pure leaf container" — every one of its children (in the whole
     * GLPI tree, not just this period's tickets) is itself childless, like
     * "Afirme" (children: Edificios, Multivendor, neither with descendants).
     * A mixed department bucket like "OP > CE" (client branches such as
     * Afirme/Sellcom BBVA WITH descendants, sitting alongside standalone
     * categories like Cattri/VoxPop as siblings) does not qualify, so those
     * standalone categories keep their own bar instead of collapsing into
     * the department and losing visibility.
     *
     * @return array<int,string> itilcategories_id => group label, present
     *     ONLY for ids that were actually promoted to a parent — an id
     *     absent from this map is not grouped (caller falls back to its own
     *     category name), which is also how callers detect promotion.
     */
    private function categoryGroupLabelsFor(BaseConnection $db, array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'itilcategories_id'))));
        if ($ids === [] || ! $db->tableExists('glpi_itilcategories')) {
            return [];
        }

        // The whole tree, not scoped to this period: whether a category is a
        // "pure leaf container" is a structural fact about GLPI's category
        // tree, independent of which leaves happen to have tickets this month.
        $cols   = $db->getFieldNames('glpi_itilcategories');
        $hasCn  = in_array('completename', $cols, true);
        $select = 'id, name, itilcategories_id' . ($hasCn ? ', completename' : '');
        $all    = $db->table('glpi_itilcategories')->select($select)->get()->getResultArray();

        $index      = []; // id => ['label' => completename|name, 'parent' => int]
        $childrenOf = []; // parentId => [childId, ...]
        foreach ($all as $row) {
            $id     = (int) $row['id'];
            $parent = (int) ($row['itilcategories_id'] ?? 0);
            $index[$id] = ['label' => trim((string) ($row['completename'] ?? $row['name'])), 'parent' => $parent];
            if ($parent > 0) {
                $childrenOf[$parent][] = $id;
            }
        }

        $hasChildren = [];
        foreach ($index as $id => $_) {
            $hasChildren[$id] = ! empty($childrenOf[$id]);
        }

        $isPureLeafContainer = [];
        foreach ($childrenOf as $parent => $children) {
            $isPureLeafContainer[$parent] = true;
            foreach ($children as $child) {
                if ($hasChildren[$child]) {
                    $isPureLeafContainer[$parent] = false;
                    break;
                }
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (! isset($index[$id])) {
                continue;
            }
            $parent = $index[$id]['parent'];
            if ($parent > 0 && ($isPureLeafContainer[$parent] ?? false) && isset($index[$parent])) {
                $out[$id] = $index[$parent]['label'];
            }
        }
        return $out;
    }

    /**
     * Bulk-resolves every configured logical field, grouped by its own
     * container so each is fetched from its data table exactly once
     * regardless of how many logical keys share it.
     *
     * @param int[] $ticketIds
     * @return array{0:array<int,array<string,?string>>,1:array<string,bool>} [ticket id => logical field => value, logical field => resolved?]
     */
    private function fieldDataFor(BaseConnection $db, array $ticketIds): array
    {
        $bindings = $this->settings->glpiFieldBindings();
        $out      = [];
        $resolved = array_fill_keys(self::LOGICAL_FIELDS, false);

        if ($ticketIds === []) {
            return [$out, $resolved];
        }

        // Group logical keys by container so a container shared by several
        // keys (or, in this deployment, one container per key) is queried once.
        $byContainer = [];
        foreach (self::LOGICAL_FIELDS as $logicalKey) {
            $binding = $bindings[$logicalKey] ?? null;
            if ($binding === null || $binding['container_id'] <= 0 || $binding['field'] === '') {
                continue;
            }
            $byContainer[$binding['container_id']][$logicalKey] = $binding['field'];
        }

        foreach ($byContainer as $containerId => $fieldsWanted) {
            $container = $this->introspector->container($containerId);
            if ($container === null || ! $db->tableExists($container['dataTable'])) {
                continue;
            }

            $fieldsByName = [];
            foreach ($container['fields'] as $f) {
                $fieldsByName[$f['name']] = $f;
            }

            $rows = $db->table($container['dataTable'])->whereIn('items_id', $ticketIds)->get()->getResultArray();

            foreach ($fieldsWanted as $logicalKey => $fieldName) {
                if (! isset($fieldsByName[$fieldName])) {
                    continue;
                }
                $resolved[$logicalKey] = true;
            }

            foreach ($rows as $row) {
                $ticketId = (int) $row['items_id'];
                $out[$ticketId] ??= [];
                foreach ($fieldsWanted as $logicalKey => $fieldName) {
                    $field = $fieldsByName[$fieldName] ?? null;
                    if ($field === null || ! array_key_exists($field['column'], $row)) {
                        $out[$ticketId][$logicalKey] = null;
                        continue;
                    }
                    $raw = $row[$field['column']];
                    if ($field['type'] === 'dropdown') {
                        $id = (int) $raw;
                        $out[$ticketId][$logicalKey] = $id > 0 ? $this->values->dropdownName($field['dropdownTable'], $id) : null;
                    } else {
                        $out[$ticketId][$logicalKey] = $raw !== null && $raw !== '' ? (string) $raw : null;
                    }
                }
            }
        }

        // A ticket may have a row in one container but not another (e.g. no
        // IDS entry while it does have client data), leaving that ticket's
        // entry short of the keys the other container would have supplied.
        // Backfill with null so every entry always carries every logical key.
        $defaults = array_fill_keys(self::LOGICAL_FIELDS, null);
        foreach ($out as &$entry) {
            $entry += $defaults;
        }
        unset($entry);

        return [$out, $resolved];
    }

    // ------------------------------------------------------------------
    // Aggregation — mirrors GlpiKpiCalculator::compute() where applicable.
    // ------------------------------------------------------------------

    /**
     * @param array<int,array<string,mixed>> $tickets flattened rows
     * @param array<string,bool>             $resolved logical field => is its binding configured?
     */
    private function aggregate(array $tickets, int $slaHours, array $resolved): array
    {
        $total    = count($tickets);
        $cerrados = count(array_filter($tickets, static fn($t) => $t['closed']));
        $enCurso  = $total - $cerrados;

        $withHours = array_filter($tickets, static fn($t) => $t['closed'] && $t['horas_resolucion'] !== null);
        $nValid    = count($withHours);
        $promH     = $nValid > 0 ? round(array_sum(array_column($withHours, 'horas_resolucion')) / $nValid, 1) : 0.0;
        $onTime    = count(array_filter($withHours, static fn($t) => $t['horas_resolucion'] <= $slaHours));
        $slaPct    = $nValid > 0 ? round($onTime / $nValid * 100, 2) : 0.0;

        // Kept in workflow order (Nuevo -> ... -> Cerrado), not by count: the
        // dashboard renders this as a single ordinal bar (lightest = earliest
        // stage, darkest = closed), so the sequence has to be the pipeline's,
        // not a popularity ranking.
        $estadosTicket = $this->orderedByStatus($tickets);

        $notEnvios = static fn(?string $cat) => self::matchesCategoryCondition($cat, notEnvios: true, notRegional: false);
        $notReg    = static fn(?string $cat) => self::matchesCategoryCondition($cat, notEnvios: false, notRegional: true);

        $regApplicable = array_filter($tickets, static fn($t) => $notReg($t['categoria']));
        $sinReg        = count(array_filter($regApplicable, static fn($t) => $t['regional'] === null));
        $regUniverse   = count($regApplicable);
        $regTop        = $this->ranking($regApplicable, 'regional', 8);

        $notEnviosSet = array_filter($tickets, static fn($t) => $notEnvios($t['categoria']));
        $sinIds       = count(array_filter($notEnviosSet, static fn($t) => $t['ids'] === null || $t['ids'] === GlpiSchema::IDC_UNASSIGNED));

        // Estado geográfico: top and bottom, same treatment as IDS — a
        // regional map is as useful for "who has the least load" as for
        // "who has the most", so both ends ship rather than just a top-N.
        $estAll = $this->ranking($tickets, 'estado_geo', null);
        $estTop = array_slice($estAll, 0, 10);
        $estAsc = $estAll;
        usort($estAsc, static fn($a, $b) => $a[1] <=> $b[1] ?: strcmp($a[0], $b[0]));
        $estBottom = array_slice($estAsc, 0, 10);

        // Categorías: 'categoria_group' ya trae, por ticket, la rama correcta
        // según la estructura real del árbol GLPI (ver
        // categoryGroupLabelsFor()) — una rama-cliente real como "Afirme" se
        // colapsa en una barra, pero una categoría suelta bajo un
        // departamento mixto como "OP > CE" (Cattri, VoxPop, ...) conserva su
        // propia barra en vez de perderse dentro del departamento. Un grupo
        // con 2+ hojas además trae su propio desglose (ver
        // categoryRankingWithChildren()): la barra del grupo da la magnitud,
        // y justo debajo se ve de qué hojas se compone, para dimensionar sin
        // perder la vista agregada.
        $catTop       = $this->categoryRankingWithChildren($tickets);
        $catLeafTotal = count(array_unique(array_filter(array_column($tickets, 'categoria'))));

        // IDS top/bottom: canonicalized via the shared KPIsOperativos
        // technician catalog (same homologation the legacy CSV pipeline used
        // for its "IDC" column — the catalog itself keeps that internal
        // name, only the GLPI-facing term changed to "IDS").
        $idsCounts = [];
        foreach ($notEnviosSet as $t) {
            $raw = $t['ids'];
            if ($raw === null || $raw === GlpiSchema::IDC_UNASSIGNED) {
                continue;
            }
            $label = $this->kpisBridge->canonicalIdcName($raw) ?? $raw;
            $idsCounts[$label] = ($idsCounts[$label] ?? 0) + 1;
        }
        $idsAll = [];
        foreach ($idsCounts as $label => $n) {
            $idsAll[] = [$label, $n];
        }
        usort($idsAll, static fn($a, $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));
        $idsTop = array_slice($idsAll, 0, 10);

        $idsAsc = $idsAll;
        usort($idsAsc, static fn($a, $b) => $a[1] <=> $b[1] ?: strcmp($a[0], $b[0]));
        $idsBottom = array_slice($idsAsc, 0, 10);

        // Envíos sub-pipeline: category contains "ENVI".
        $envSet   = array_filter($tickets, static fn($t) => $t['categoria'] !== null && mb_stripos($t['categoria'], GlpiSchema::ENVIOS_CATEGORY_SUBSTRING) !== false);
        $envTotal = count($envSet);
        $envCerr  = count(array_filter($envSet, static fn($t) => $t['closed']));
        $envPend  = $envTotal - $envCerr;
        $envPct   = $envTotal > 0 ? round($envCerr / $envTotal * 100, 2) : 0.0;

        // Coordination: regional counts (full, not just top 8) + coordinator map.
        $regAllCounts = [];
        foreach ($regApplicable as $t) {
            if ($t['regional'] === null) {
                continue;
            }
            $regAllCounts[$t['regional']] = ($regAllCounts[$t['regional']] ?? 0) + 1;
        }
        arsort($regAllCounts);
        $coordTickets = $regAllCounts;

        $coordMap  = $this->kpisBridge->coordinatorMap();
        $coordInfo = [];
        foreach ($coordTickets as $zone => $_count) {
            $normKey = KpisOperativosBridge::normalizeZone((string) $zone);
            $coordInfo[$zone] = isset($coordMap[$normKey])
                ? ['coord' => $coordMap[$normKey]['coord'], 'gte' => $coordMap[$normKey]['gte']]
                : ['coord' => $zone, 'gte' => '-'];
        }

        return [
            'available'          => true,
            'fields_resolved'    => $resolved,
            'client_data_available' => $resolved['regional'] || $resolved['estado_geo'] || $resolved['municipio'] || $resolved['sucursal'],
            'ids_available'      => $resolved['ids'],
            'total'          => $total,
            'cerrados'       => $cerrados,
            'en_curso'       => $enCurso,
            'tasa_cierre'    => $total > 0 ? round($cerrados / $total * 100, 2) : 0.0,
            'sla_pct'        => $slaPct,
            'prom_h'         => $promH,
            'sin_reg'        => $sinReg,
            'sin_ids'        => $sinIds,
            'reg_universe'   => $regUniverse,
            'reg_top'        => $regTop,
            'est_top'        => $estTop,
            'est_bottom'     => $estBottom,
            'ids_top'        => $idsTop,
            'ids_bottom'     => $idsBottom,
            'cat_top'        => $catTop,
            'cat_leaf_total' => $catLeafTotal,
            'estados_ticket' => $estadosTicket,
            'env_total'      => $envTotal,
            'env_cerr'       => $envCerr,
            'env_pend'       => $envPend,
            'env_pct'        => $envPct,
            'coord_tickets'  => $coordTickets,
            'coord_info'     => $coordInfo,
        ];
    }

    /** @param array<int,array<string,mixed>> $tickets @return list<array{0:string,1:int}> */
    private function ranking(array $tickets, string $field, ?int $limit): array
    {
        $counts = [];
        foreach ($tickets as $t) {
            $v = $t[$field] ?? null;
            if ($v === null) {
                continue;
            }
            $counts[$v] = ($counts[$v] ?? 0) + 1;
        }
        $out = [];
        foreach ($counts as $label => $n) {
            $out[] = [(string) $label, $n];
        }
        usort($out, static fn($a, $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));
        return $limit !== null ? array_slice($out, 0, $limit) : $out;
    }

    /**
     * Category ranking with the group/child breakdown a grouped branch needs
     * to stay "dimensionable": a group's bar gives the magnitude, and right
     * under it its own leaves (Afirme -> Edificios, Multivendor) — sorted by
     * count, short-labeled to the leaf's own name — so grouping never hides
     * how that total is actually made up. A group with only ONE leaf isn't
     * broken out (its total already equals that leaf's, nothing to add) and
     * a leaf that was never promoted (see categoryGroupLabelsFor) ships as a
     * plain 'single' row, unchanged from before grouping existed.
     *
     * @param array<int,array<string,mixed>> $tickets
     * @return list<array{label:string,value:int,tier:'group'|'child'|'single'}>
     */
    private function categoryRankingWithChildren(array $tickets): array
    {
        $groups = []; // groupKey => ['total' => int, 'children' => [leafLabel => count]]
        foreach ($tickets as $t) {
            $leaf = $t['categoria'] ?? null;
            if ($leaf === null) {
                continue;
            }
            $promotedGroup = $t['categoria_group'] ?? null;
            $key = $promotedGroup ?? $leaf;

            $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + 1;
            if ($promotedGroup !== null) {
                $groups[$key]['children'][$leaf] = ($groups[$key]['children'][$leaf] ?? 0) + 1;
            }
        }

        uksort($groups, static fn($a, $b) => strcmp((string) $a, (string) $b)); // stable tiebreak before the total sort
        uasort($groups, static fn($a, $b) => $b['total'] <=> $a['total']);

        $out = [];
        foreach ($groups as $label => $data) {
            $children = $data['children'] ?? [];
            if (count($children) < 2) {
                $out[] = ['label' => (string) $label, 'value' => $data['total'], 'tier' => 'single'];
                continue;
            }

            $out[] = ['label' => (string) $label, 'value' => $data['total'], 'tier' => 'group'];

            arsort($children);
            foreach ($children as $leafLabel => $count) {
                // Last two segments, not just the leaf's own name: two
                // different clients can both have an "Edificios" leaf, and a
                // bare "Edificios" label would collide across groups (both
                // visually and in any map keyed by label, e.g. PPTX charts).
                $segments  = explode(' > ', $leafLabel);
                $shortName = count($segments) >= 2 ? implode(' > ', array_slice($segments, -2)) : $leafLabel;
                $out[] = ['label' => $shortName, 'value' => $count, 'tier' => 'child'];
            }
        }
        return $out;
    }

    /**
     * Ticket counts by status, in GLPI's own workflow order (Nuevo through
     * Cerrado) rather than by count — statuses with zero tickets in the
     * period are left out, order is preserved among the rest.
     *
     * @param array<int,array<string,mixed>> $tickets
     * @return list<array{0:string,1:int}>
     */
    private function orderedByStatus(array $tickets): array
    {
        $counts = [];
        foreach ($tickets as $t) {
            $counts[$t['estado']] = ($counts[$t['estado']] ?? 0) + 1;
        }
        $out = [];
        foreach (self::STATUS_LABELS as $label) {
            if (isset($counts[$label])) {
                $out[] = [$label, $counts[$label]];
            }
        }
        return $out;
    }

    /** PHP mirror of GlpiSchema's notEnviosSqlCondition/notRegionalApplicableSqlCondition. */
    private static function matchesCategoryCondition(?string $categoria, bool $notEnvios, bool $notRegional): bool
    {
        if ($categoria === null) {
            return true;
        }
        $cat = mb_strtoupper($categoria, 'UTF-8');
        if (mb_stripos($cat, mb_strtoupper(GlpiSchema::ENVIOS_CATEGORY_SUBSTRING, 'UTF-8')) !== false) {
            return false;
        }
        if (mb_stripos($cat, mb_strtoupper(GlpiSchema::ALMACEN_CATEGORY_SUBSTRING, 'UTF-8')) !== false) {
            return false;
        }
        if ($notRegional && mb_stripos($cat, mb_strtoupper(GlpiSchema::LAB_SUBCAT, 'UTF-8')) !== false) {
            return false;
        }
        return true;
    }

    private function emptySnapshot(): array
    {
        return [
            'available' => true,
            'fields_resolved' => array_fill_keys(self::LOGICAL_FIELDS, false),
            'client_data_available' => false, 'ids_available' => false,
            'total' => 0, 'cerrados' => 0, 'en_curso' => 0, 'tasa_cierre' => 0.0,
            'sla_pct' => 0.0, 'prom_h' => 0.0,
            'sin_reg' => 0, 'sin_ids' => 0, 'reg_universe' => 0,
            'reg_top' => [], 'est_top' => [], 'est_bottom' => [], 'ids_top' => [], 'ids_bottom' => [],
            'cat_top' => [], 'cat_leaf_total' => 0, 'estados_ticket' => [],
            'env_total' => 0, 'env_cerr' => 0, 'env_pend' => 0, 'env_pct' => 0.0,
            'coord_tickets' => new \stdClass(),
            'coord_info'    => new \stdClass(),
        ];
    }
}
