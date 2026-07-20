<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Communications\Services\MailerService;
use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskBacklogAreaModel;
use App\Modules\ServiceDesk\Models\ServiceDeskBacklogRunModel;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Daily GLPI open-ticket backlog report: reads the live backlog straight from
 * GLPI's DB, aggregates it into KPIs, age buckets and per-area/subcategory
 * summaries, renders the HTML email + an xlsx dump, and mails it to a configured
 * audience with a report-specific sender.
 *
 * Reuses the existing GLPI DB connection (Provisioning), the live plugin schema
 * (GlpiSchemaIntrospector) to resolve the "IDC" column, and the Communications
 * mailer for delivery. Nothing about the backlog is hardcoded — statuses, areas
 * and thresholds are config/DB driven.
 */
class BacklogReportService
{
    /** Label for in-scope tickets that lack a regional value (data gap). */
    private const SIN_REGIONAL = 'Sin regional';

    public function __construct(
        private GlpiDbConnection $glpi,
        private GlpiSchemaIntrospector $introspector,
        private ServiceDeskSettings $settings,
        private ServiceDeskConfig $config,
        private ServiceDeskBacklogAreaModel $areaMap,
        private ServiceDeskBacklogRunModel $runs,
        private MailerService $mailer,
        private ServiceDeskCategoryMapModel $categoryMap,
    ) {}

    public function isConfigured(): bool
    {
        return $this->glpi->isConfigured();
    }

    // ------------------------------------------------------------------
    // Scheduling entry point (called by the cron command)
    // ------------------------------------------------------------------

    /**
     * Sends the report if the cut-off hour has arrived and it has not gone out
     * today yet. Safe to call every few minutes from cron.
     */
    public function runScheduled(): ServiceResult
    {
        if (! $this->settings->backlogEnabled()) {
            return ServiceResult::fail('El reporte de backlog está deshabilitado.');
        }
        if (! $this->settings->backlogReady()) {
            return ServiceResult::fail('Falta configuración (destinatarios o remitente).');
        }

        $today = date('Y-m-d');
        $dow   = (int) date('N'); // 6=Sat, 7=Sun
        if (! $this->settings->backlogWeekends() && $dow >= 6) {
            return ServiceResult::fail('Hoy es fin de semana y el envío en fin de semana está desactivado.');
        }

        // Already sent (guard against duplicate cron ticks).
        if ($this->settings->backlogLastSentDate() === $today || $this->runs->sentOn($today)) {
            return ServiceResult::fail('El reporte de hoy ya fue enviado.');
        }

        // Only fire once the configured cut-off hour has been reached.
        if (date('H:i') < $this->settings->backlogSendHour()) {
            return ServiceResult::fail('Aún no es la hora de corte configurada (' . $this->settings->backlogSendHour() . ').');
        }

        $result = $this->send('scheduled', null);
        if ($result->success) {
            $this->settings->setBacklogLastSentDate($today);
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // Build + render + send
    // ------------------------------------------------------------------

    /**
     * Builds, renders and mails one report. $trigger ∈ scheduled|manual|test.
     * For 'test' the audience is overridden with $testTo.
     *
     * @param string[] $testTo
     */
    public function send(string $trigger, ?int $userId, array $testTo = []): ServiceResult
    {
        if (! $this->isConfigured()) {
            return ServiceResult::fail('La conexión a GLPI no está configurada.');
        }

        $to = $trigger === 'test' && $testTo !== [] ? $testTo : $this->settings->backlogTo();
        $cc = $trigger === 'test' ? [] : $this->settings->backlogCc();

        if ($to === []) {
            return ServiceResult::fail('No hay destinatarios configurados.');
        }

        $xlsxPath = null;
        try {
            $data     = $this->build();
            $html     = $this->renderHtml($data);
            $xlsxPath = $this->buildXlsx($data);

            $subject = $this->settings->backlogSubjectPrefix() . ' - ' . date('d/m/Y', $data['cutoffTs']);

            $res = $this->mailer->sendReport(
                $to,
                $cc,
                $this->settings->backlogFromEmail(),
                $this->settings->backlogFromName(),
                $subject,
                $html,
                [['path' => $xlsxPath, 'name' => 'backlog_' . date('Y-m-d', $data['cutoffTs']) . '.xlsx']],
            );

            $this->runs->record([
                'report_date'  => date('Y-m-d', $data['cutoffTs']),
                'trigger'      => $trigger,
                'status'       => $res['success'] ? 'ok' : 'failed',
                'total_open'   => $data['total'],
                'recipients'   => implode(', ', array_merge($to, $cc)),
                'error'        => $res['success'] ? null : mb_substr((string) $res['error'], 0, 1000),
                'triggered_by' => $userId,
            ]);

            return $res['success']
                ? ServiceResult::ok(['total' => $data['total']], 'Reporte enviado a ' . count($to) . ' destinatario(s).')
                : ServiceResult::fail('No se pudo enviar el correo: ' . $res['error']);
        } catch (\Throwable $e) {
            log_message('error', '[BacklogReport] ' . $e->getMessage());
            $this->runs->record([
                'report_date'  => date('Y-m-d'),
                'trigger'      => $trigger,
                'status'       => 'failed',
                'total_open'   => 0,
                'recipients'   => implode(', ', array_merge($to, $cc)),
                'error'        => mb_substr($e->getMessage(), 0, 1000),
                'triggered_by' => $userId,
            ]);
            return ServiceResult::fail('Error al generar el reporte: ' . $e->getMessage());
        } finally {
            if ($xlsxPath !== null && is_file($xlsxPath)) {
                @unlink($xlsxPath);
            }
        }
    }

    /**
     * Reads GLPI and returns the full aggregated report data structure.
     */
    public function build(): array
    {
        $db  = $this->glpi->connection();
        $now = time();

        $statusLabels = $this->config->glpiStatusLabels;        // id => real GLPI label
        $typeLabels   = $this->config->glpiTypeLabels;          // id => Incidencia/Requerimiento
        $statusIds    = array_values($this->config->backlogOpenStatuses);
        $criticalDays = $this->settings->backlogCriticalDays();
        $pendingId    = $this->config->backlogPendingStatus;

        // 1) Open backlog tickets. externalid is only selected if present (it is
        // not a native column in every GLPI version).
        $ticketCols = $db->getFieldNames('glpi_tickets');
        $hasExternalId = in_array('externalid', $ticketCols, true);
        $select = 'id, name, status, type, itilcategories_id, date'
            . ($hasExternalId ? ', externalid' : '');
        $tickets = $db->table('glpi_tickets')
            ->select($select)
            ->where('is_deleted', 0)
            ->whereIn('status', $statusIds)
            ->orderBy('date', 'ASC')
            ->get()->getResultArray();

        // 2) Category index (root + subcategory resolution).
        $catIndex = $this->loadCategoryIndex($db);
        $areaAssign = $this->areaMap->assignments(); // rootCategoryId => areaKey

        // 3) IDC values (ticket id => IDC label; presence = has IDC).
        $idcValues = $this->settings->backlogIdcConfigured()
            ? $this->loadTicketFieldValues($db, $this->settings->backlogIdcContainerId(), $this->settings->backlogIdcField())
            : null;
        $idcConfigured = $idcValues !== null;

        // 4) Regional values (ticket id => regional label) for the "POR REGIONAL" table.
        $regionalValues = $this->settings->backlogRegionalConfigured()
            ? $this->loadTicketFieldValues($db, $this->settings->backlogRegionalContainerId(), $this->settings->backlogRegionalField())
            : null;
        $regionalConfigured = $regionalValues !== null;
        $regAgg = []; // regional label => ['tickets','sumAge','sinIdc']
        // Categories the "POR REGIONAL" table is restricted to (subtree match).
        // Empty = no restriction (all categories count).
        $regionalCatSet   = array_flip($this->categoryMap->regionalIds());
        $regionalScopeMemo = [];
        // Categories the "Sin IDC" metric applies to (independent subtree scope).
        $idcCatSet    = array_flip($this->categoryMap->idcScopeIds());
        $idcScopeMemo = [];
        $idcScopeTotal = 0; // open tickets within the IDC scope (KPI denominator)
        // "POR CLIENTE": client name from the `cliente` column (CLIENTE para el
        // título), grouped for tickets in the client scope (independent flag).
        $clienteMap        = $this->categoryMap->clienteMap();     // catId => cliente
        $clienteCatSet     = array_flip($this->categoryMap->clienteScopeIds());
        $clienteScopeMemo  = [];
        $clienteResolveMemo = [];
        $cliAgg = []; // cliente => [total,sumAge,maxAge,asignada,planificada,enEspera,sinIdc]

        // Prime area accumulators.
        $bucketDefs = $this->config->backlogAgeBuckets();
        $emptyBuckets = [];
        foreach ($bucketDefs as $b) {
            $emptyBuckets[$b['key']] = 0;
        }
        $areas = [];
        $areaOrder = array_keys($this->config->backlogAreas);
        $emptyArea = static fn(string $label, array $buckets): array => [
            'label'         => $label,
            'total'         => 0,
            'critical'      => 0,
            'pending'       => 0,
            'sinIdc'        => 0,
            'buckets'       => $buckets,
            'subcategories' => [],
            'oldest'        => [],
        ];
        foreach ($this->config->backlogAreas as $key => $label) {
            $areas[$key] = $emptyArea($label, $emptyBuckets);
        }
        $areas['sin_clasificar'] = $emptyArea('Sin clasificar', $emptyBuckets);
        $areaOrder[] = 'sin_clasificar';

        $total = count($tickets);
        $pending = 0;
        $critical = 0;
        $sinIdc = 0;
        $rows = [];

        foreach ($tickets as $t) {
            $id      = (int) $t['id'];
            $catId   = (int) $t['itilcategories_id'];
            // GLPI stores unset dates as '0000-00-00 00:00:00'; strtotime() turns
            // that into an ancient (valid) timestamp, so guard it explicitly to
            // avoid absurd ages inflating criticals/buckets/avg.
            $dateStr = trim((string) ($t['date'] ?? ''));
            $open    = ($dateStr === '' || strncmp($dateStr, '0000-00-00', 10) === 0)
                ? 0
                : (int) (strtotime($dateStr) ?: 0);
            $ageDays = $open > 0 ? max(0, (int) floor(($now - $open) / 86400)) : 0;
            $status  = (int) $t['status'];

            if ($status === $pendingId) {
                $pending++;
            }
            if ($ageDays >= $criticalDays) {
                $critical++;
            }

            // "Sin IDC" only applies to tickets within the IDC category scope
            // (empty scope = all). Out-of-scope tickets never count as sin IDC.
            $inIdcScope = $idcCatSet === []
                || $this->categoryInSet($catId, $idcCatSet, $catIndex, $idcScopeMemo);
            $countsForIdc = $idcConfigured && $inIdcScope;
            $hasIdc = $idcConfigured ? isset($idcValues[$id]) : true;
            if ($countsForIdc) {
                $idcScopeTotal++;
                if (! $hasIdc) {
                    $sinIdc++;
                }
            }

            // Regional value for the dump (always shown when configured).
            $regional = $regionalConfigured ? ($regionalValues[$id] ?? '') : '';

            // Regional aggregation (POR REGIONAL) is restricted to the flagged
            // categories via subtree match; empty flagged set = all categories.
            // In-scope tickets WITHOUT a regional value are grouped under
            // "Sin regional" so the gap (a ticket that should have one) is visible.
            $inRegionalScope = $regionalCatSet === []
                || $this->categoryInSet($catId, $regionalCatSet, $catIndex, $regionalScopeMemo);
            if ($regionalConfigured && $inRegionalScope) {
                $bucket = $regional !== '' ? $regional : self::SIN_REGIONAL;
                $agg = $regAgg[$bucket] ?? ['tickets' => 0, 'sumAge' => 0, 'sinIdc' => 0];
                $agg['tickets']++;
                $agg['sumAge'] += $ageDays;
                if ($countsForIdc && ! $hasIdc) {
                    $agg['sinIdc']++;
                }
                $regAgg[$bucket] = $agg;
            }

            // Client (POR CLIENTE): name from the `cliente` column, subtree-resolved.
            // Grouped only for tickets in the client scope (empty scope = all).
            $clienteName = $clienteMap === []
                ? ''
                : $this->resolveClienteFor($catId, $clienteMap, $catIndex, $clienteResolveMemo);
            $inClienteScope = $clienteCatSet === []
                || $this->categoryInSet($catId, $clienteCatSet, $catIndex, $clienteScopeMemo);
            if ($clienteName !== '' && $inClienteScope) {
                $ca = $cliAgg[$clienteName] ?? [
                    'total' => 0, 'sumAge' => 0, 'maxAge' => 0,
                    'asignada' => 0, 'planificada' => 0, 'enEspera' => 0, 'sinIdc' => 0,
                ];
                $ca['total']++;
                $ca['sumAge'] += $ageDays;
                $ca['maxAge']  = max($ca['maxAge'], $ageDays);
                // Asignada = Nuevo(1)+En curso(2); Planificada = 3; En espera = 4.
                if ($status === 3) {
                    $ca['planificada']++;
                } elseif ($status === 4) {
                    $ca['enEspera']++;
                } else {
                    $ca['asignada']++;
                }
                if ($countsForIdc && ! $hasIdc) {
                    $ca['sinIdc']++;
                }
                $cliAgg[$clienteName] = $ca;
            }

            // Area + subcategory from the category tree.
            [$rootId, $subName, $leafName] = $this->resolveCategory($catId, $catIndex);
            $areaKey = $areaAssign[$rootId] ?? 'sin_clasificar';
            if (! isset($areas[$areaKey])) {
                $areaKey = 'sin_clasificar';
            }

            $bucketKey = $this->bucketFor($ageDays, $bucketDefs);
            $areas[$areaKey]['total']++;
            $areas[$areaKey]['buckets'][$bucketKey]++;
            $sub = $subName !== '' ? $subName : ($leafName !== '' ? $leafName : 'Sin categoría');
            $areas[$areaKey]['subcategories'][$sub] = ($areas[$areaKey]['subcategories'][$sub] ?? 0) + 1;

            // Per-area KPIs mirror the global ones so each area gets its own
            // "cuadro resumen" (total, críticos, en espera, sin IDC).
            if ($ageDays >= $criticalDays) {
                $areas[$areaKey]['critical']++;
            }
            if ($status === $pendingId) {
                $areas[$areaKey]['pending']++;
            }
            if ($countsForIdc && ! $hasIdc) {
                $areas[$areaKey]['sinIdc']++;
            }
            // Tickets are fetched ordered by date ASC, so the first 5 seen per
            // area are its 5 oldest open tickets (top-5 más antiguos).
            if (count($areas[$areaKey]['oldest']) < 5) {
                $areas[$areaKey]['oldest'][] = [
                    'id'     => $id,
                    'title'  => (string) $t['name'],
                    'status' => $statusLabels[$status] ?? (string) $status,
                    'age'    => $ageDays,
                    'open'   => $open > 0 ? date('d/m/Y', $open) : '',
                ];
            }

            // Full ITIL completename for the export (e.g. "OP > CE > Pop Media").
            $categoryFull = $catId > 0 && isset($catIndex[$catId])
                ? ($catIndex[$catId]['completename'] ?: $leafName)
                : '';

            $rows[] = [
                'id'         => $id,
                'title'      => (string) $t['name'],
                'type'       => $typeLabels[(int) $t['type']] ?? '',
                'status'     => $statusLabels[$status] ?? (string) $status,
                'area'       => $areas[$areaKey]['label'],
                'category'   => $categoryFull,
                'cliente'    => $clienteName ?? '',
                'externalid' => $hasExternalId ? (string) ($t['externalid'] ?? '') : '',
                'regional'   => $regional,
                // Real IDC value when configured (empty = sin IDC / no aplica).
                'idc'        => $idcConfigured ? (string) ($idcValues[$id] ?? '') : '',
                'open'       => $open > 0 ? date('d/m/Y H:i', $open) : '',
                'age'        => $ageDays,
            ];
        }

        // Regionals sorted by ticket count desc.
        $regionals = [];
        foreach ($regAgg as $name => $agg) {
            $regionals[] = [
                'name'    => $name,
                'tickets' => $agg['tickets'],
                'avgDays' => round($agg['sumAge'] / max(1, $agg['tickets']), 1),
                'sinIdc'  => $agg['sinIdc'],
            ];
        }
        // Sort by ticket count desc, but always keep "Sin regional" last (it is a
        // residual/data-quality row, not a real regional).
        usort($regionals, static function ($a, $b) {
            $aSin = $a['name'] === self::SIN_REGIONAL;
            $bSin = $b['name'] === self::SIN_REGIONAL;
            if ($aSin !== $bSin) {
                return $aSin <=> $bSin;
            }
            return $b['tickets'] <=> $a['tickets'];
        });

        // Clients (POR CLIENTE) sorted by total desc.
        $clients = [];
        foreach ($cliAgg as $name => $ca) {
            $clients[] = [
                'name'        => $name,
                'total'       => $ca['total'],
                'avgDays'     => round($ca['sumAge'] / max(1, $ca['total']), 1),
                'maxDays'     => $ca['maxAge'],
                'asignada'    => $ca['asignada'],
                'planificada' => $ca['planificada'],
                'enEspera'    => $ca['enEspera'],
                'sinIdc'      => $ca['sinIdc'],
            ];
        }
        usort($clients, static fn($a, $b) => $b['total'] <=> $a['total']);

        // Sort each area's subcategories by count desc.
        foreach ($areas as &$a) {
            arsort($a['subcategories']);
        }
        unset($a);

        // Same-day activity per area (tickets created / closed today, net Δ).
        $daily = $this->buildDailyActivity($db, $catIndex, $areaAssign, $now);

        return [
            'cutoffTs'      => $now,
            'cutoffLabel'   => date('d \d\e F \d\e Y', $now),
            'orgLabel'      => $this->settings->backlogOrgLabel(),
            'total'         => $total,
            'pending'       => $pending,
            'critical'      => $critical,
            'criticalDays'  => $criticalDays,
            'sinIdc'        => $idcConfigured ? $sinIdc : null,
            'idcConfigured' => $idcConfigured,
            'idcScopeTotal' => $idcScopeTotal,
            'regionals'         => $regionals,
            'regionalConfigured' => $regionalConfigured,
            'clients'           => $clients,
            'areas'         => $areas,
            'areaOrder'     => $areaOrder,
            'daily'         => $daily,
            'dailyLabel'    => date('d/m/Y', $now),
            'buckets'       => $bucketDefs,
            'rows'          => $rows,
        ];
    }

    public function renderHtml(array $data): string
    {
        return view('App\Modules\ServiceDesk\Views\emails\backlog_report', $data);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Loads glpi_itilcategories into id => [name, parent, sub, leaf] where 'sub'
     * is the 2nd completename segment (direct child of root) and 'leaf' the full
     * complete name's last segment.
     *
     * @return array<int,array{name:string,parent:int,completename:string}>
     */
    private function loadCategoryIndex($db): array
    {
        if (! $db->tableExists('glpi_itilcategories')) {
            return [];
        }
        $cols   = $db->getFieldNames('glpi_itilcategories');
        $hasCn  = in_array('completename', $cols, true);
        $select = 'id, name, itilcategories_id' . ($hasCn ? ', completename' : '');

        $rows = $db->table('glpi_itilcategories')->select($select)->get()->getResultArray();
        $index = [];
        foreach ($rows as $r) {
            $index[(int) $r['id']] = [
                'name'         => trim((string) $r['name']),
                'parent'       => (int) ($r['itilcategories_id'] ?? 0),
                'completename' => trim((string) ($r['completename'] ?? $r['name'])),
            ];
        }
        return $index;
    }

    /**
     * Resolves a category id to [rootId, subName, leafName]. Root is found by
     * walking parents; sub/leaf come from the completename segments (GLPI uses
     * " > " between levels).
     *
     * @return array{0:int,1:string,2:string}
     */
    private function resolveCategory(int $catId, array $index): array
    {
        if ($catId <= 0 || ! isset($index[$catId])) {
            return [0, '', ''];
        }

        // Walk up to the root (parent = 0), with a cycle guard.
        $rootId = $catId;
        $seen   = [];
        while (isset($index[$rootId]) && $index[$rootId]['parent'] > 0 && ! isset($seen[$rootId])) {
            $seen[$rootId] = true;
            $rootId = $index[$rootId]['parent'];
        }

        $segments = preg_split('/\s*>\s*/', $index[$catId]['completename']) ?: [];
        $leafName = trim((string) end($segments)) ?: $index[$catId]['name'];
        $subName  = isset($segments[1]) ? trim((string) $segments[1]) : '';

        return [$rootId, $subName, $leafName];
    }

    /**
     * True when a category equals or descends from any id in $set (subtree
     * match): walks the parent chain and checks membership. Memoized per id.
     *
     * @param array<int,mixed> $set  flagged category ids as keys
     * @param array<int,array> $index category index (id => [...,'parent'=>int])
     * @param array<int,bool>  $memo
     */
    private function categoryInSet(int $catId, array $set, array $index, array &$memo): bool
    {
        if ($catId <= 0) {
            return false;
        }
        if (isset($memo[$catId])) {
            return $memo[$catId];
        }

        $node    = $catId;
        $seen    = [];
        $inSet   = false;
        while ($node > 0 && isset($index[$node]) && ! isset($seen[$node])) {
            if (isset($set[$node])) {
                $inSet = true;
                break;
            }
            $seen[$node] = true;
            $node = $index[$node]['parent'];
        }

        return $memo[$catId] = $inSet;
    }

    /**
     * Resolves the client label for a category: the `cliente` of the category or
     * of its nearest ancestor that has one (subtree inheritance). Memoized.
     *
     * @param array<int,string> $clienteMap catId => cliente
     * @param array<int,array>  $index      category index (id => [...,'parent'=>int])
     * @param array<int,string> $memo
     */
    private function resolveClienteFor(int $catId, array $clienteMap, array $index, array &$memo): string
    {
        if ($catId <= 0) {
            return '';
        }
        if (isset($memo[$catId])) {
            return $memo[$catId];
        }

        $node = $catId;
        $seen = [];
        $found = '';
        while ($node > 0 && isset($index[$node]) && ! isset($seen[$node])) {
            if (($clienteMap[$node] ?? '') !== '') {
                $found = $clienteMap[$node];
                break;
            }
            $seen[$node] = true;
            $node = $index[$node]['parent'];
        }

        return $memo[$catId] = $found;
    }

    /**
     * Same-day activity per area: tickets CREATED and CLOSED between 00:00 today
     * and the cut-off ($now). "Closed" = reached Resuelto(5) or Cerrado(6) with a
     * solve/close timestamp inside the window (SuperAdmin decision). Net = created
     * − closed, i.e. how much each area's backlog grew (+) or shrank (−) today.
     *
     * A separate query is required because build()'s ticket set only holds OPEN
     * statuses, so same-day closures are not in it.
     *
     * @param array<int,array>  $catIndex   category index (id => [...,'parent'])
     * @param array<int,string> $areaAssign rootCategoryId => areaKey
     * @return array<string,array{label:string,created:int,closed:int,net:int}>
     */
    private function buildDailyActivity($db, array $catIndex, array $areaAssign, int $now): array
    {
        $startStr = date('Y-m-d 00:00:00', $now);
        $endStr   = date('Y-m-d H:i:s', $now);

        $daily = [];
        foreach ($this->config->backlogAreas as $key => $label) {
            $daily[$key] = ['label' => $label, 'created' => 0, 'closed' => 0, 'net' => 0];
        }
        $daily['sin_clasificar'] = ['label' => 'Sin clasificar', 'created' => 0, 'closed' => 0, 'net' => 0];

        // Category id => area key, resolved via the root category (memoized).
        $memo = [];
        $areaKeyFor = function (int $catId) use ($catIndex, $areaAssign, &$memo, $daily): string {
            if (isset($memo[$catId])) {
                return $memo[$catId];
            }
            [$rootId] = $this->resolveCategory($catId, $catIndex);
            $key = $areaAssign[$rootId] ?? 'sin_clasificar';
            if (! isset($daily[$key])) {
                $key = 'sin_clasificar';
            }
            return $memo[$catId] = $key;
        };

        // Created today (any status).
        $created = $db->table('glpi_tickets')
            ->select('itilcategories_id')
            ->where('is_deleted', 0)
            ->where('date >=', $startStr)
            ->where('date <=', $endStr)
            ->get()->getResultArray();
        foreach ($created as $t) {
            $daily[$areaKeyFor((int) $t['itilcategories_id'])]['created']++;
        }

        // Closed today: Resuelto/Cerrado with solve OR close date inside the window.
        $cols     = $db->getFieldNames('glpi_tickets');
        $hasSolve = in_array('solvedate', $cols, true);
        $hasClose = in_array('closedate', $cols, true);
        if ($hasSolve || $hasClose) {
            $b = $db->table('glpi_tickets')
                ->select('itilcategories_id')
                ->where('is_deleted', 0)
                ->whereIn('status', [5, 6])
                ->groupStart();
            $needOr = false;
            if ($hasSolve) {
                $b->groupStart()->where('solvedate >=', $startStr)->where('solvedate <=', $endStr)->groupEnd();
                $needOr = true;
            }
            if ($hasClose) {
                $inner = $needOr ? $b->orGroupStart() : $b->groupStart();
                $inner->where('closedate >=', $startStr)->where('closedate <=', $endStr)->groupEnd();
            }
            $closed = $b->groupEnd()->get()->getResultArray();
            foreach ($closed as $t) {
                $daily[$areaKeyFor((int) $t['itilcategories_id'])]['closed']++;
            }
        }

        foreach ($daily as &$a) {
            $a['net'] = $a['created'] - $a['closed'];
        }
        unset($a);

        return $daily;
    }

    private function bucketFor(int $ageDays, array $bucketDefs): string
    {
        foreach ($bucketDefs as $b) {
            if ($ageDays >= $b['min'] && ($b['max'] === null || $ageDays <= $b['max'])) {
                return $b['key'];
            }
        }
        return $bucketDefs[array_key_last($bucketDefs)]['key'];
    }

    /**
     * Maps ticket id => non-empty value of a plugin (Additional Fields) column.
     * For dropdown fields the FK is resolved to its label; for scalar fields the
     * raw value is used. Tickets with no row or an empty value are omitted.
     *
     * Returns null when the container/field can't be resolved, so the caller
     * skips the derived KPI/section rather than reporting bogus data.
     *
     * @return array<int,string>|null  ticket id => label
     */
    private function loadTicketFieldValues($db, int $containerId, string $fieldName): ?array
    {
        if ($containerId <= 0 || $fieldName === '') {
            return null;
        }

        $container = $this->introspector->container($containerId);
        if ($container === null) {
            log_message('warning', "[BacklogReport] plugin container #{$containerId} not found — field '{$fieldName}' skipped.");
            return null;
        }

        $field = null;
        foreach ($container['fields'] as $f) {
            if ($f['name'] === $fieldName) {
                $field = $f;
                break;
            }
        }
        if ($field === null || ! $db->tableExists($container['dataTable'])) {
            log_message('warning', "[BacklogReport] field '{$fieldName}' unresolved — skipped.");
            return null;
        }

        $column = $field['column'];
        $rows   = $db->table($container['dataTable'])
            ->select("items_id, {$column} AS val")
            ->where('itemtype', 'Ticket')
            ->get()->getResultArray();

        // Dropdown -> resolve FK id to its label once.
        $labels = null;
        if ($field['type'] === 'dropdown' && $field['dropdownTable'] !== null && $db->tableExists($field['dropdownTable'])) {
            $dcols   = $db->getFieldNames($field['dropdownTable']);
            $nameCol = in_array('completename', $dcols, true) ? 'completename' : 'name';
            $labels  = [];
            foreach ($db->table($field['dropdownTable'])->select("id, {$nameCol} AS n")->get()->getResultArray() as $d) {
                $labels[(int) $d['id']] = trim((string) $d['n']);
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) $r['items_id'];
            if ($labels !== null) {
                $fk = (int) $r['val'];
                if ($fk > 0 && ($labels[$fk] ?? '') !== '') {
                    $out[$tid] = $labels[$fk];
                }
            } else {
                $v = trim((string) $r['val']);
                if ($v !== '' && $v !== '0') {
                    $out[$tid] = $v;
                }
            }
        }
        return $out;
    }

    /**
     * Writes the full backlog to an xlsx file and returns its path.
     */
    private function buildXlsx(array $data): string
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Backlog');

        // Column order per spec (subcategoría intentionally omitted from the export).
        $headers = ['ID', 'TÍTULO', 'FECHA APERTURA', 'TIPO', 'ESTATUS', 'ÁREA', 'CATEGORÍA', 'CLIENTE', 'ID EXTERNO', 'REGIONAL', 'IDC', 'DÍAS ABIERTO'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:L1');
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1773C8');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $str = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $r = 2;
        foreach ($data['rows'] as $row) {
            $sheet->setCellValue("A{$r}", $row['id']);
            $sheet->setCellValueExplicit("B{$r}", $row['title'], $str);
            $sheet->setCellValueExplicit("C{$r}", $row['open'], $str);
            $sheet->setCellValue("D{$r}", $row['type']);
            $sheet->setCellValue("E{$r}", $row['status']);
            $sheet->setCellValue("F{$r}", $row['area']);
            $sheet->setCellValueExplicit("G{$r}", $row['category'], $str);
            $sheet->setCellValueExplicit("H{$r}", $row['cliente'], $str);
            $sheet->setCellValueExplicit("I{$r}", $row['externalid'], $str);
            $sheet->setCellValue("J{$r}", $row['regional']);
            $sheet->setCellValueExplicit("K{$r}", $row['idc'], $str);
            $sheet->setCellValue("L{$r}", $row['age']);
            $r++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $path = rtrim(sys_get_temp_dir(), '/') . '/backlog_' . date('Y-m-d', $data['cutoffTs']) . '_' . bin2hex(random_bytes(4)) . '.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
