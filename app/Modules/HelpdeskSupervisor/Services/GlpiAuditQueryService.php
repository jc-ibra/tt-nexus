<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Services\GlpiSchemaIntrospector;
use CodeIgniter\Database\BaseConnection;

/**
 * Builds and runs the batch queries against GLPI for an audit. Everything is
 * fetched per agent+period in bulk (never ticket by ticket) and returned in
 * plain arrays keyed by ticket id, so AuditRunnerService can assemble the
 * normalized ticket structures the rules consume.
 *
 * Reuses Provisioning's GLPI connection (same instance) and ServiceDesk's schema
 * introspector for the plugin Additional Fields containers.
 */
class GlpiAuditQueryService
{
    /** GLPI ticket status codes. */
    public const STATUS_NEW       = 1;
    public const STATUS_ASSIGNED  = 2; // En curso (asignada)
    public const STATUS_PLANNED   = 3; // En curso (planificada)
    public const STATUS_WAITING   = 4; // En espera
    public const STATUS_SOLVED    = 5; // Resuelto
    public const STATUS_CLOSED    = 6; // Cerrado

    /** Statuses considered "open" (still the agent's responsibility). */
    public const OPEN_STATUSES = [self::STATUS_NEW, self::STATUS_ASSIGNED, self::STATUS_PLANNED, self::STATUS_WAITING];

    /** tickets_users link type for the assigned technician. */
    private const LINK_ASSIGNED = 2;

    public function __construct(
        private GlpiDbConnection $glpi,
        private GlpiSchemaIntrospector $introspector,
        private HelpdeskSupervisorSettings $settings,
    ) {}

    public function isConfigured(): bool
    {
        return $this->glpi->isConfigured();
    }

    private function db(): BaseConnection
    {
        return $this->glpi->connection();
    }

    // ------------------------------------------------------------------
    // Tickets
    // ------------------------------------------------------------------

    /**
     * Tickets opened in a period where the agent is the requester (solicitante).
     * Uses glpi_tickets_users type=1 and the same Resumen GLPI scope (types,
     * entity, category) so audit/KPI denominators match overview Solicitantes.
     *
     * @return array<int,array<string,mixed>> keyed by ticket id
     */
    public function ticketsForPeriod(int $agentGlpiUserId, string $periodStart, string $periodEnd): array
    {
        $db = $this->db();
        if (! $db->tableExists('glpi_tickets_users')) {
            return $this->ticketsForPeriodByRecipient($agentGlpiUserId, $periodStart, $periodEnd);
        }

        $types       = $this->settings->overviewTicketTypes();
        $entityIds   = GlpiTicketScope::resolveEntityIds($db, $this->settings);
        $categoryIds = GlpiTicketScope::resolveCategoryScopeIds($db, $this->settings);

        $builder = $db->table('glpi_tickets t')
            ->select('t.id, t.name, t.date, t.date_creation, t.date_mod, t.status, t.type, t.itilcategories_id, t.externalid', false)
            ->join(
                'glpi_tickets_users tu',
                'tu.tickets_id = t.id AND tu.type = ' . GlpiTicketScope::LINK_REQUESTER,
                'inner',
            )
            ->where('tu.users_id', $agentGlpiUserId)
            ->where('t.is_deleted', 0)
            ->groupBy('t.id, t.name, t.date, t.date_creation, t.date_mod, t.status, t.type, t.itilcategories_id, t.externalid')
            ->orderBy('t.id', 'ASC');

        GlpiTicketScope::apply($builder, null, $types, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        return $this->mapTicketRows($builder->get()->getResultArray());
    }

    /**
     * Fallback when glpi_tickets_users is unavailable (legacy GLPI).
     *
     * @return array<int,array<string,mixed>>
     */
    private function ticketsForPeriodByRecipient(int $agentGlpiUserId, string $periodStart, string $periodEnd): array
    {
        $db          = $this->db();
        $types       = $this->settings->overviewTicketTypes();
        $entityIds   = GlpiTicketScope::resolveEntityIds($db, $this->settings);
        $categoryIds = GlpiTicketScope::resolveCategoryScopeIds($db, $this->settings);

        $builder = $db->table('glpi_tickets t')
            ->select('t.id, t.name, t.date, t.date_creation, t.date_mod, t.status, t.type, t.itilcategories_id, t.externalid', false)
            ->where('t.users_id_recipient', $agentGlpiUserId)
            ->where('t.is_deleted', 0)
            ->orderBy('t.id', 'ASC');

        GlpiTicketScope::apply($builder, null, $types, $entityIds, $categoryIds, $periodStart, $periodEnd, 't');

        return $this->mapTicketRows($builder->get()->getResultArray());
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function mapTicketRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = [
                'id'                => (int) $r['id'],
                'name'              => (string) $r['name'],
                'date'              => (string) $r['date'],
                'date_creation'     => (string) $r['date_creation'],
                'date_mod'          => (string) $r['date_mod'],
                'status'            => (int) $r['status'],
                'type'              => (int) $r['type'],
                'itilcategories_id' => (int) $r['itilcategories_id'],
                'external_id'       => (string) ($r['externalid'] ?? ''),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Assignments (tickets_users type = 2)
    // ------------------------------------------------------------------

    /**
     * @param int[] $ticketIds
     * @return array<int,int[]> ticket id => assigned user ids
     */
    public function assignmentsForTickets(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }
        $rows = $this->db()->table('glpi_tickets_users')
            ->select('tickets_id, users_id')
            ->whereIn('tickets_id', $ticketIds)
            ->where('type', self::LINK_ASSIGNED)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['tickets_id']][] = (int) $r['users_id'];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Change logs
    // ------------------------------------------------------------------

    /**
     * @param int[] $ticketIds
     * @return array<int,array<int,array<string,mixed>>> ticket id => log rows
     */
    public function logsForTickets(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }
        $rows = $this->db()->table('glpi_logs')
            ->select('items_id, id_search_option, old_value, new_value, date_mod, user_name')
            ->where('itemtype', 'Ticket')
            ->whereIn('items_id', $ticketIds)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['items_id']][] = [
                'id_search_option' => (int) $r['id_search_option'],
                'old_value'        => (string) $r['old_value'],
                'new_value'        => (string) $r['new_value'],
                'date_mod'         => (string) $r['date_mod'],
                'user_name'        => (string) $r['user_name'],
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Activity: followups, tasks, solutions
    // ------------------------------------------------------------------

    /**
     * Per-ticket activity counts, distinguishing total vs. the audited agent's
     * own updates (for the ownership/followup rules).
     *
     * @param int[] $ticketIds
     * @return array<int,array{followups:int,tasks:int,solutions:int,agent_updates:int,last_agent_activity:?string}>
     */
    public function activityForTickets(array $ticketIds, int $agentGlpiUserId): array
    {
        $out = [];
        foreach ($ticketIds as $id) {
            $out[(int) $id] = [
                'followups' => 0, 'tasks' => 0, 'solutions' => 0,
                'agent_updates' => 0, 'last_agent_activity' => null,
            ];
        }
        if ($ticketIds === []) {
            return $out;
        }

        $db = $this->db();

        // Followups (itemtype-based table).
        $this->accumulate(
            $db->table('glpi_itilfollowups')
                ->select('items_id AS tid, users_id, date_creation')
                ->where('itemtype', 'Ticket')
                ->whereIn('items_id', $ticketIds)
                ->get()->getResultArray(),
            $out, 'followups', $agentGlpiUserId
        );

        // Tasks (tickets_id-based table).
        $this->accumulate(
            $db->table('glpi_tickettasks')
                ->select('tickets_id AS tid, users_id, date_creation')
                ->whereIn('tickets_id', $ticketIds)
                ->get()->getResultArray(),
            $out, 'tasks', $agentGlpiUserId
        );

        // Solutions (itemtype-based table).
        $this->accumulate(
            $db->table('glpi_itilsolutions')
                ->select('items_id AS tid, users_id, date_creation')
                ->where('itemtype', 'Ticket')
                ->whereIn('items_id', $ticketIds)
                ->get()->getResultArray(),
            $out, 'solutions', $agentGlpiUserId
        );

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>>                                   $rows
     * @param array<int,array{followups:int,tasks:int,solutions:int,agent_updates:int,last_agent_activity:?string}> $out
     */
    private function accumulate(array $rows, array &$out, string $bucket, int $agentGlpiUserId): void
    {
        foreach ($rows as $r) {
            $tid = (int) $r['tid'];
            if (! isset($out[$tid])) {
                continue;
            }
            $out[$tid][$bucket]++;
            if ((int) $r['users_id'] === $agentGlpiUserId) {
                $out[$tid]['agent_updates']++;
                $date = (string) ($r['date_creation'] ?? '');
                if ($date !== '' && ($out[$tid]['last_agent_activity'] === null || $date > $out[$tid]['last_agent_activity'])) {
                    $out[$tid]['last_agent_activity'] = $date;
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Plugin Additional Fields (tab data)
    // ------------------------------------------------------------------

    /**
     * Raw data-table rows for a plugin container, keyed by ticket id. The
     * container meta comes from the introspector (has dataTable + fields).
     *
     * @param array<string,mixed> $container introspector container meta
     * @param int[]               $ticketIds
     * @return array<int,array<string,mixed>>
     */
    public function pluginRowsForContainer(array $container, array $ticketIds): array
    {
        $dataTable = (string) ($container['dataTable'] ?? '');
        if ($dataTable === '' || $ticketIds === []) {
            return [];
        }
        $db = $this->db();
        if (! $db->tableExists($dataTable)) {
            return [];
        }

        $rows = $db->table($dataTable)
            ->whereIn('items_id', $ticketIds)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['items_id']] = $r;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Reference lookups
    // ------------------------------------------------------------------

    /**
     * itilcategories_id => completename.
     *
     * @return array<int,string>
     */
    public function categoriesIndex(): array
    {
        $out = [];
        foreach ($this->introspector->categories() as $c) {
            $out[(int) $c['id']] = (string) $c['name'];
        }
        return $out;
    }

    /** GLPI login name for an agent id (to match glpi_logs.user_name), or ''. */
    public function agentUserName(int $glpiUserId): string
    {
        $row = $this->db()->table('glpi_users')->select('name')->where('id', $glpiUserId)->get()->getRow();
        return $row ? (string) $row->name : '';
    }

    /** "Realname Firstname" display name for an agent id, or ''. */
    public function agentDisplayName(int $glpiUserId): string
    {
        $row = $this->db()->table('glpi_users')
            ->select('realname, firstname, name')
            ->where('id', $glpiUserId)->get()->getRow();
        if (! $row) {
            return '';
        }
        $name = trim(trim((string) $row->realname) . ' ' . trim((string) $row->firstname));
        return $name !== '' ? $name : (string) $row->name;
    }

    /**
     * id => name for a plugin dropdown table, so rules can resolve FK values to
     * their human labels (e.g. the "Estado" tab field).
     *
     * @return array<int,string>
     */
    public function dropdownValues(string $dropdownTable): array
    {
        $db = $this->db();
        if ($dropdownTable === '' || ! $db->tableExists($dropdownTable)) {
            return [];
        }
        $rows = $db->table($dropdownTable)->select('id, name')->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $out;
    }

    /**
     * Resolves a GLPI user id from a display name ("Realname Firstname" or
     * "Firstname Realname"), used to map coordinator names to ids. Returns null
     * when there is no unambiguous match.
     */
    public function resolveUserIdByName(string $displayName): ?int
    {
        $needle = $this->normalizeName($displayName);
        if ($needle === '') {
            return null;
        }
        $rows = $this->db()->table('glpi_users')
            ->select('id, realname, firstname, name')
            ->where('is_active', 1)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $a = $this->normalizeName(trim((string) $r['realname']) . ' ' . trim((string) $r['firstname']));
            $b = $this->normalizeName(trim((string) $r['firstname']) . ' ' . trim((string) $r['realname']));
            if ($needle === $a || $needle === $b) {
                return (int) $r['id'];
            }
        }
        return null;
    }

    /** Lowercased, accent-folded, space-collapsed name for comparison. */
    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }
}
