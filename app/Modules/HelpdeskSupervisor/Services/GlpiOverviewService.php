<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;

/**
 * Live aggregate mirror of GLPI tickets for the HelpdeskSupervisor Resumen.
 * Two modes: backlog (open now) and period (tickets opened in a date range).
 * Only COUNTs / GROUP BYs — never ticket rows.
 */
class GlpiOverviewService
{
    public const STATUS_LABELS = [
        1 => 'Nuevo',
        2 => 'En curso',
        3 => 'Planificada',
        4 => 'En espera',
        5 => 'Resuelto',
        6 => 'Cerrado',
    ];

    public const TYPE_LABELS = [
        1 => 'Incidencia',
        2 => 'Requerimiento',
    ];

    /** GLPI tickets_users.type for the assigned technician (asignado). */
    private const LINK_ASSIGNED = 2;

    private const CACHE_PREFIX = 'helpdesk_supervisor_glpi_overview_';

    public function __construct(
        private GlpiDbConnection $glpi,
        private HelpdeskSupervisorSettings $settings,
    ) {}

    public function isConfigured(): bool
    {
        return $this->glpi->isConfigured();
    }

    /**
     * @param string      $mode          backlog|period
     * @param string|null $periodStart   YYYY-MM-DD (required for period)
     * @param string|null $periodEnd     YYYY-MM-DD (required for period)
     */
    public function build(
        bool $forceRefresh = false,
        string $mode = 'backlog',
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): ServiceResult {
        if (! $this->isConfigured()) {
            return ServiceResult::fail('La conexión a GLPI no está configurada.');
        }

        $mode = $mode === 'period' ? 'period' : 'backlog';
        if ($mode === 'period') {
            if ($periodStart === null || $periodEnd === null
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
                return ServiceResult::fail('El modo período requiere fechas válidas (YYYY-MM-DD).');
            }
            if ($periodStart > $periodEnd) {
                return ServiceResult::fail('La fecha inicial no puede ser posterior a la final.');
            }
        } else {
            $periodStart = null;
            $periodEnd   = null;
        }

        $cacheKey = self::CACHE_PREFIX . $mode . '_' . ($periodStart ?? '') . '_' . ($periodEnd ?? '');
        $ttl      = $this->settings->overviewCacheTtl();
        if (! $forceRefresh && $ttl > 0) {
            $cached = cache()->get($cacheKey);
            if (is_array($cached)) {
                $cached['from_cache'] = true;
                return ServiceResult::ok($cached);
            }
        }

        try {
            $data = $this->compute($mode, $periodStart, $periodEnd);
        } catch (\Throwable $e) {
            log_message('error', '[HelpdeskSupervisor] overview failed: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo consultar GLPI: ' . $e->getMessage());
        }

        $data['mode']         = $mode;
        $data['period_start'] = $periodStart;
        $data['period_end']   = $periodEnd;
        $data['from_cache']   = false;
        $data['generated_at'] = date('c');

        if ($ttl > 0) {
            cache()->save($cacheKey, $data, $ttl);
        }

        return ServiceResult::ok($data);
    }

    public function invalidateCache(): void
    {
        // Backlog key is fixed; period keys expire by TTL. Wipe the common one.
        cache()->delete(self::CACHE_PREFIX . 'backlog__');
        // Also wipe any previously cached period for the current month as a courtesy.
        $start = date('Y-m-01');
        $end   = date('Y-m-t');
        cache()->delete(self::CACHE_PREFIX . 'period_' . $start . '_' . $end);
    }

    /**
     * GLPI web portal base (no trailing slash). Reads provisioning_systems glpi
     * base_url / portal_url — same source as /admin/provisioning/systems.
     */
    public function glpiPortalUrl(): string
    {
        try {
            $system = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->findByKey('glpi');
            if (is_array($system)) {
                $options = [];
                if (! empty($system['options'])) {
                    $decoded = json_decode((string) $system['options'], true);
                    $options = is_array($decoded) ? $decoded : [];
                }
                foreach (['portal_url', 'login_url'] as $key) {
                    $url = trim((string) ($options[$key] ?? ''));
                    if ($url !== '') {
                        return rtrim($url, '/');
                    }
                }
                $url = trim((string) ($system['base_url'] ?? ''));
                if ($url !== '') {
                    return rtrim((string) preg_replace('#/apirest\.php/?$#i', '', $url), '/');
                }
            }
        } catch (\Throwable) {
            // fall through
        }
        try {
            $s   = service('provisioningSettings')->getAll();
            $url = trim((string) ($s['glpi_url'] ?? $s['glpi_base_url'] ?? ''));
            if ($url !== '') {
                return rtrim((string) preg_replace('#/apirest\.php/?$#i', '', $url), '/');
            }
        } catch (\Throwable) {
            // fall through
        }
        return 'https://helpdesk.trantortechnologies.mx';
    }

    public function ticketUrl(int $ticketId): string
    {
        return $this->glpiPortalUrl() . '/front/ticket.form.php?id=' . $ticketId;
    }

    /**
     * Lightweight ticket list for overview drill-down (paginated).
     *
     * @param string $dimension category|source|requester
     */
    public function listTickets(
        string $dimension,
        int $filterId,
        string $mode = 'backlog',
        ?string $periodStart = null,
        ?string $periodEnd = null,
        int $page = 1,
        int $perPage = 50,
    ): ServiceResult {
        if (! $this->isConfigured()) {
            return ServiceResult::fail('La conexión a GLPI no está configurada.');
        }
        if (! in_array($dimension, ['category', 'source', 'requester', 'assignee', 'status', 'type', 'backlog', 'still_open', 'critical', 'period'], true)) {
            return ServiceResult::fail('Dimensión de filtro no válida.');
        }
        $mode = $mode === 'period' ? 'period' : 'backlog';
        if ($dimension === 'still_open' && $mode !== 'period') {
            return ServiceResult::fail('El filtro "aún abiertos" solo aplica en modo período.');
        }
        if ($dimension === 'backlog' && $mode !== 'backlog') {
            return ServiceResult::fail('El filtro backlog solo aplica en modo backlog.');
        }
        if ($mode === 'period') {
            if ($periodStart === null || $periodEnd === null
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
                return ServiceResult::fail('Fechas de período inválidas.');
            }
        } else {
            $periodStart = null;
            $periodEnd   = null;
        }

        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        try {
            $payload = $this->fetchTicketList($dimension, $filterId, $mode, $periodStart, $periodEnd, $page, $perPage);
        } catch (\Throwable $e) {
            log_message('error', '[HelpdeskSupervisor] listTickets: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo consultar GLPI: ' . $e->getMessage());
        }

        return ServiceResult::ok($payload);
    }

    /** All tickets for export (capped). */
    public function listTicketsForExport(
        string $dimension,
        int $filterId,
        string $mode = 'backlog',
        ?string $periodStart = null,
        ?string $periodEnd = null,
        int $maxRows = 10000,
    ): ServiceResult {
        $result = $this->listTickets($dimension, $filterId, $mode, $periodStart, $periodEnd, 1, $maxRows);
        if (! $result->success) {
            return $result;
        }
        $data = is_array($result->data) ? $result->data : [];
        if (! empty($data['total']) && (int) $data['total'] > $maxRows) {
            return ServiceResult::fail(
                'Hay más de ' . number_format($maxRows) . ' tickets. Acota el filtro en configuración antes de exportar.'
            );
        }
        return $result;
    }

    /**
     * @return array{
     *   tickets:list<array{id:int,title:string,status:int,status_label:string,date:string,category_id:int,category_label:string}>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   total_pages:int
     * }
     */
    private function fetchTicketList(
        string $dimension,
        int $filterId,
        string $mode,
        ?string $periodStart,
        ?string $periodEnd,
        int $page,
        int $perPage,
    ): array {
        $total  = (int) $this->ticketListBuilder($dimension, $filterId, $mode, $periodStart, $periodEnd)
            ->countAllResults();
        $offset = ($page - 1) * $perPage;

        $rows = $this->ticketListBuilder($dimension, $filterId, $mode, $periodStart, $periodEnd)
            ->orderBy('t.date', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $catIds   = array_map(static fn(array $r) => (int) $r['itilcategories_id'], $rows);
        $catNames = $this->categoryNames($this->db(), $catIds);

        $tickets = [];
        foreach ($rows as $r) {
            $status = (int) $r['status'];
            $catId  = (int) $r['itilcategories_id'];
            $tickets[] = [
                'id'             => (int) $r['id'],
                'title'          => (string) $r['name'],
                'status'         => $status,
                'status_label'   => self::STATUS_LABELS[$status] ?? ('Estatus ' . $status),
                'date'           => (string) $r['date'],
                'category_id'    => $catId,
                'category_label' => $catId === 0
                    ? '(Sin categoría)'
                    : ($catNames[$catId] ?? ('Categoría #' . $catId)),
            ];
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        return [
            'tickets'     => $tickets,
            'total'       => $total,
            'page'        => min($page, $totalPages),
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    private function ticketListBuilder(
        string $dimension,
        int $filterId,
        string $mode,
        ?string $periodStart,
        ?string $periodEnd,
    ): BaseBuilder {
        $db           = $this->db();
        $openStatuses = $this->settings->overviewOpenStatuses();
        $types        = $this->settings->overviewTicketTypes();
        $entityIds    = GlpiTicketScope::resolveEntityIds($db, $this->settings);
        $categoryIds  = GlpiTicketScope::resolveCategoryScopeIds($db, $this->settings);
        $statusFilter = $mode === 'backlog' ? $openStatuses : null;
        $typeFilter   = $types;

        if ($dimension === 'requester') {
            $builder = $db->table('glpi_tickets t')
                ->select('t.id, t.name, t.status, t.date, t.itilcategories_id', false)
                ->join('glpi_tickets_users tu', 'tu.tickets_id = t.id AND tu.type = ' . GlpiTicketScope::LINK_REQUESTER, 'inner')
                ->where('t.is_deleted', 0)
                ->where('tu.users_id', $filterId);
        } elseif ($dimension === 'assignee') {
            $builder = $db->table('glpi_tickets t')
                ->select('t.id, t.name, t.status, t.date, t.itilcategories_id', false)
                ->join('glpi_tickets_users tu', 'tu.tickets_id = t.id AND tu.type = ' . self::LINK_ASSIGNED, 'inner')
                ->where('t.is_deleted', 0)
                ->where('tu.users_id', $filterId);
        } else {
            $builder = $db->table('glpi_tickets t')
                ->select('t.id, t.name, t.status, t.date, t.itilcategories_id', false)
                ->where('t.is_deleted', 0);

            switch ($dimension) {
                case 'category':
                    $builder->where('t.itilcategories_id', $filterId);
                    break;
                case 'source':
                    $builder->where('t.requesttypes_id', $filterId);
                    break;
                case 'status':
                    $builder->where('t.status', $filterId);
                    $statusFilter = null;
                    break;
                case 'type':
                    $builder->where('t.type', $filterId);
                    $typeFilter   = [$filterId];
                    break;
                case 'backlog':
                    // Open backlog — statusFilter stays openStatuses
                    break;
                case 'still_open':
                    $statusFilter = $openStatuses;
                    break;
                case 'period':
                    $statusFilter = null;
                    break;
                case 'critical':
                    $criticalDays = $this->settings->overviewCriticalDays();
                    $cutoff       = date('Y-m-d H:i:s', time() - ($criticalDays * 86400));
                    $builder->whereIn('t.status', $openStatuses)
                        ->where('t.date <', $cutoff)
                        ->where('t.date NOT LIKE \'0000-00-00%\'', null, false);
                    $statusFilter = null;
                    break;
                default:
                    $builder->where('t.itilcategories_id', $filterId);
            }
        }

        GlpiTicketScope::apply($builder, $statusFilter, $typeFilter, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        return $builder;
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function listEntities(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }
        try {
            $db = $this->db();
            if (! $db->tableExists('glpi_entities')) {
                return [];
            }
            $cols  = $db->getFieldNames('glpi_entities');
            $label = in_array('completename', $cols, true) ? 'completename' : 'name';
            $rows  = $db->table('glpi_entities')
                ->select("id, {$label} AS label")
                ->orderBy($label, 'ASC')
                ->get()->getResultArray();

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id'    => (int) $r['id'],
                    'label' => (string) ($r['label'] ?? ('#' . $r['id'])),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            log_message('warning', '[HelpdeskSupervisor] listEntities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function listRootCategories(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }
        try {
            $db = $this->db();
            if (! $db->tableExists('glpi_itilcategories')) {
                return [];
            }
            $cols  = $db->getFieldNames('glpi_itilcategories');
            $label = in_array('completename', $cols, true) ? 'completename' : 'name';
            $builder = $db->table('glpi_itilcategories')->select("id, {$label} AS label");
            if (in_array('itilcategories_id', $cols, true)) {
                $builder->where('itilcategories_id', 0);
            }
            if (in_array('is_deleted', $cols, true)) {
                $builder->where('is_deleted', 0);
            }
            $rows = $builder->orderBy($label, 'ASC')->get()->getResultArray();

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id'    => (int) $r['id'],
                    'label' => (string) ($r['label'] ?? ('#' . $r['id'])),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            log_message('warning', '[HelpdeskSupervisor] listRootCategories: ' . $e->getMessage());
            return [];
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function db(): BaseConnection
    {
        return $this->glpi->connection();
    }

    /**
     * @return array<string,mixed>
     */
    private function compute(string $mode, ?string $periodStart, ?string $periodEnd): array
    {
        $db           = $this->db();
        $openStatuses = $this->settings->overviewOpenStatuses();
        $types        = $this->settings->overviewTicketTypes();
        $criticalDays = $this->settings->overviewCriticalDays();
        $topCat       = $this->settings->overviewTopNCategories();
        $topReq       = $this->settings->overviewTopNRequesters();
        $topAssign    = $this->settings->overviewTopNAssignees();
        $entityIds    = GlpiTicketScope::resolveEntityIds($db, $this->settings);
        $categoryIds  = GlpiTicketScope::resolveCategoryScopeIds($db, $this->settings);

        // Backlog: only open statuses. Period: any status (tickets opened in range).
        $statusFilter = $mode === 'backlog' ? $openStatuses : null;
        $statusDisplay = $mode === 'backlog' ? $openStatuses : array_keys(self::STATUS_LABELS);

        $filters = [
            'entities_mode'      => $this->settings->overviewEntitiesMode(),
            'entities_id'        => $this->settings->overviewEntitiesId(),
            'entities_recursive' => $this->settings->overviewEntitiesRecursive(),
            'entity_ids_applied' => $entityIds,
            'open_statuses'      => $openStatuses,
            'ticket_types'       => $types,
            'category_roots'     => $this->settings->overviewCategoryRoots(),
            'category_ids_count' => $categoryIds === null ? null : count($categoryIds),
            'critical_days'      => $criticalDays,
        ];

        $byStatus   = $this->groupCount($db, 'status', $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd);
        $statusRows = [];
        foreach ($statusDisplay as $sid) {
            $sid   = (int) $sid;
            $count = (int) ($byStatus[$sid] ?? 0);
            if ($mode === 'period' && $count === 0) {
                continue;
            }
            $statusRows[] = [
                'id'    => $sid,
                'label' => self::STATUS_LABELS[$sid] ?? ('Estatus ' . $sid),
                'count' => $count,
            ];
        }
        if ($mode === 'period') {
            foreach ($byStatus as $sid => $count) {
                if (isset(self::STATUS_LABELS[(int) $sid])) {
                    continue;
                }
                $statusRows[] = [
                    'id'    => (int) $sid,
                    'label' => 'Estatus ' . $sid,
                    'count' => (int) $count,
                ];
            }
        }

        $total = $mode === 'period'
            ? (int) array_sum($byStatus)
            : array_sum(array_column($statusRows, 'count'));

        $stillOpen = 0;
        foreach ($openStatuses as $sid) {
            $stillOpen += (int) ($byStatus[$sid] ?? 0);
        }

        $critical = $this->countCritical(
            $db,
            $openStatuses,
            $types,
            $entityIds,
            $categoryIds,
            $criticalDays,
            $periodStart,
            $periodEnd,
        );

        $byType   = $this->groupCount($db, 'type', $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd);
        $typeRows = [];
        foreach ($types as $tid) {
            $typeRows[] = [
                'id'    => $tid,
                'label' => self::TYPE_LABELS[$tid] ?? ('Tipo ' . $tid),
                'count' => (int) ($byType[$tid] ?? 0),
            ];
        }

        return [
            'summary' => [
                'total'         => $total,
                'still_open'    => $stillOpen,
                'critical'      => $critical,
                'critical_days' => $criticalDays,
            ],
            // legacy key kept for any consumer expecting backlog.*
            'backlog' => [
                'total'         => $total,
                'critical'      => $critical,
                'critical_days' => $criticalDays,
            ],
            'by_status'     => $statusRows,
            'by_type'       => $typeRows,
            'by_category'   => $this->topCategories($db, $statusFilter, $types, $entityIds, $categoryIds, $topCat, $periodStart, $periodEnd),
            'by_source'     => $this->allSources($db, $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd),
            'by_requester'  => $this->topRequesters($db, $statusFilter, $types, $entityIds, $categoryIds, $topReq, $periodStart, $periodEnd),
            'by_assignee'   => $this->topAssignees($db, $statusFilter, $types, $entityIds, $categoryIds, $topAssign, $periodStart, $periodEnd),
            'filters'       => $filters,
            'cache_ttl'     => $this->settings->overviewCacheTtl(),
        ];
    }

    /**
     * @param int[]|null $statusFilter null = any status
     * @param int[]      $types
     * @param int[]|null $entityIds
     * @param int[]|null $categoryIds
     * @return array<int,int>
     */
    private function groupCount(
        BaseConnection $db,
        string $column,
        ?array $statusFilter,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        $builder = $db->table('glpi_tickets')
            ->select("{$column} AS k, COUNT(*) AS c", false)
            ->where('is_deleted', 0)
            ->groupBy($column);

        GlpiTicketScope::apply($builder, $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd);

        $rows = $builder->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['k']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * Open tickets older than criticalDays. In period mode: opened in range AND still open AND old.
     *
     * @param int[]      $openStatuses
     * @param int[]      $types
     * @param int[]|null $entityIds
     * @param int[]|null $categoryIds
     */
    private function countCritical(
        BaseConnection $db,
        array $openStatuses,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        int $criticalDays,
        ?string $periodStart,
        ?string $periodEnd,
    ): int {
        $cutoff  = date('Y-m-d H:i:s', time() - ($criticalDays * 86400));
        $builder = $db->table('glpi_tickets')
            ->where('is_deleted', 0)
            ->whereIn('status', $openStatuses)
            ->where('date <', $cutoff)
            ->where("date NOT LIKE '0000-00-00%'", null, false);

        GlpiTicketScope::apply($builder, null, $types, $entityIds, $categoryIds, $periodStart, $periodEnd);
        // status already applied above for open-only critical

        return (int) $builder->countAllResults();
    }

    /**
     * @param int[]|null $statusFilter
     * @param int[]      $types
     * @param int[]|null $entityIds
     * @param int[]|null $categoryIds
     * @return list<array{id:int,label:string,count:int}>
     */
    private function topCategories(
        BaseConnection $db,
        ?array $statusFilter,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        int $limit,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        $builder = $db->table('glpi_tickets t')
            ->select('t.itilcategories_id AS id, COUNT(*) AS c', false)
            ->where('t.is_deleted', 0)
            ->groupBy('t.itilcategories_id')
            ->orderBy('c', 'DESC')
            ->limit($limit);

        GlpiTicketScope::apply($builder, $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        $rows  = $builder->get()->getResultArray();
        $ids   = array_map(static fn($r) => (int) $r['id'], $rows);
        $names = $this->categoryNames($db, $ids);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'id'    => $id,
                'label' => $id === 0 ? '(Sin categoría)' : ($names[$id] ?? ('#' . $id)),
                'count' => (int) $r['c'],
            ];
        }
        return $out;
    }

    /**
     * @param int[]|null $statusFilter
     * @param int[]      $types
     * @param int[]|null $entityIds
     * @param int[]|null $categoryIds
     * @return list<array{id:int,label:string,count:int}>
     */
    private function allSources(
        BaseConnection $db,
        ?array $statusFilter,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        $builder = $db->table('glpi_tickets t')
            ->select('t.requesttypes_id AS id, COUNT(*) AS c', false)
            ->where('t.is_deleted', 0)
            ->groupBy('t.requesttypes_id')
            ->orderBy('c', 'DESC');

        GlpiTicketScope::apply($builder, $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        $rows  = $builder->get()->getResultArray();
        $ids   = array_map(static fn($r) => (int) $r['id'], $rows);
        $names = $this->requestTypeNames($db, $ids);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'id'    => $id,
                'label' => $id === 0 ? '(Sin origen)' : ($names[$id] ?? ('#' . $id)),
                'count' => (int) $r['c'],
            ];
        }
        return $out;
    }

    /**
     * Top requesters via glpi_tickets_users type=1 (solicitante).
     *
     * @param int[]|null $statusFilter
     * @param int[]      $types
     * @param int[]|null $entityIds
     * @param int[]|null $categoryIds
     * @return list<array{id:int,label:string,count:int}>
     */
    private function topRequesters(
        BaseConnection $db,
        ?array $statusFilter,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        int $limit,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        if (! $db->tableExists('glpi_tickets_users')) {
            return [];
        }

        $builder = $db->table('glpi_tickets t')
            ->select('tu.users_id AS id, COUNT(*) AS c', false)
            ->join('glpi_tickets_users tu', 'tu.tickets_id = t.id AND tu.type = ' . GlpiTicketScope::LINK_REQUESTER, 'inner')
            ->where('t.is_deleted', 0)
            ->groupBy('tu.users_id')
            ->orderBy('c', 'DESC')
            ->limit($limit);

        GlpiTicketScope::apply($builder, $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        $rows  = $builder->get()->getResultArray();
        $ids   = array_map(static fn($r) => (int) $r['id'], $rows);
        $names = $this->userNames($db, $ids);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'id'    => $id,
                'label' => $id === 0 ? '(Sin solicitante)' : ($names[$id] ?? ('Usuario #' . $id)),
                'count' => (int) $r['c'],
            ];
        }
        return $out;
    }

    /**
     * Top assignees via glpi_tickets_users type=2 (técnico asignado).
     *
     * @param int[]|null $statusFilter
     * @param int[]      $types
     * @param int[]|null $entityIds
     * @param int[]|null $categoryIds
     * @return list<array{id:int,label:string,count:int}>
     */
    private function topAssignees(
        BaseConnection $db,
        ?array $statusFilter,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        int $limit,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        if (! $db->tableExists('glpi_tickets_users')) {
            return [];
        }

        $builder = $db->table('glpi_tickets t')
            ->select('tu.users_id AS id, COUNT(*) AS c', false)
            ->join('glpi_tickets_users tu', 'tu.tickets_id = t.id AND tu.type = ' . self::LINK_ASSIGNED, 'inner')
            ->where('t.is_deleted', 0)
            ->groupBy('tu.users_id')
            ->orderBy('c', 'DESC')
            ->limit($limit);

        GlpiTicketScope::apply($builder, $statusFilter, $types, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        $rows  = $builder->get()->getResultArray();
        $ids   = array_map(static fn($r) => (int) $r['id'], $rows);
        $names = $this->userNames($db, $ids);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'id'    => $id,
                'label' => $id === 0 ? '(Sin asignado)' : ($names[$id] ?? ('Usuario #' . $id)),
                'count' => (int) $r['c'],
            ];
        }
        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int,string>
     */
    private function categoryNames(BaseConnection $db, array $ids): array
    {
        $ids = array_values(array_filter($ids, static fn(int $id) => $id > 0));
        if ($ids === [] || ! $db->tableExists('glpi_itilcategories')) {
            return [];
        }
        $cols  = $db->getFieldNames('glpi_itilcategories');
        $label = in_array('completename', $cols, true) ? 'completename' : 'name';
        $rows  = $db->table('glpi_itilcategories')->select("id, {$label} AS label")->whereIn('id', $ids)->get()->getResultArray();
        $out   = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (string) $r['label'];
        }
        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int,string>
     */
    private function requestTypeNames(BaseConnection $db, array $ids): array
    {
        $ids = array_values(array_filter($ids, static fn(int $id) => $id > 0));
        if ($ids === [] || ! $db->tableExists('glpi_requesttypes')) {
            return [];
        }
        $rows = $db->table('glpi_requesttypes')->select('id, name')->whereIn('id', $ids)->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int,string>
     */
    private function userNames(BaseConnection $db, array $ids): array
    {
        $ids = array_values(array_filter($ids, static fn(int $id) => $id > 0));
        if ($ids === [] || ! $db->tableExists('glpi_users')) {
            return [];
        }
        $cols = $db->getFieldNames('glpi_users');
        $hasReal = in_array('realname', $cols, true);
        $hasFirst = in_array('firstname', $cols, true);
        $select = 'id, name';
        if ($hasReal) {
            $select .= ', realname';
        }
        if ($hasFirst) {
            $select .= ', firstname';
        }
        $rows = $db->table('glpi_users')->select($select)->whereIn('id', $ids)->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $real = $hasReal ? trim((string) ($r['realname'] ?? '')) : '';
            $first = $hasFirst ? trim((string) ($r['firstname'] ?? '')) : '';
            $name = trim((string) ($r['name'] ?? ''));
            if ($real !== '' || $first !== '') {
                $label = trim($first . ' ' . $real);
            } else {
                $label = $name !== '' ? $name : ('#' . $r['id']);
            }
            $out[(int) $r['id']] = $label;
        }
        return $out;
    }
}
