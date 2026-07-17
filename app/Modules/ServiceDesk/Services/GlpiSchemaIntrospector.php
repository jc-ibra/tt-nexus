<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Provisioning\Services\GlpiCatalogService;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Reads the GLPI "Additional Fields" plugin schema LIVE and turns it into a
 * concrete import plan. This is what makes Service Desk resilient: nothing about
 * the plugin's containers, fields or dropdown tables is hardcoded — it is all
 * discovered from glpi_plugin_fields_containers / glpi_plugin_fields_fields.
 *
 * Validated field -> column mapping (GLPI Fields plugin convention):
 *   - dropdown  field "Xfield" -> FK column  plugin_fields_Xfielddropdowns_id
 *                               -> table      glpi_plugin_fields_Xfielddropdowns
 *   - scalar    field "Xfield" -> column      Xfield   (text/textarea/number/date)
 * Data table per container: glpi_plugin_fields_ticket<name>s
 */
class GlpiSchemaIntrospector
{
    private const DATA_PREFIX     = 'glpi_plugin_fields_ticket';
    private const DROPDOWN_PREFIX = 'glpi_plugin_fields_';
    private const DROPDOWN_SUFFIX = 'dropdowns';

    /** @var array<int,array>|null */
    private ?array $containerCache = null;

    public function __construct(
        private GlpiDbConnection $glpi,
        private GlpiCatalogService $catalogs,
        private ServiceDeskConfig $config,
        private ServiceDeskCategoryMapModel $categoryMap,
    ) {}

    public function isConfigured(): bool
    {
        return $this->glpi->isConfigured();
    }

    // ------------------------------------------------------------------
    // Containers + fields
    // ------------------------------------------------------------------

    /**
     * All active plugin containers attached to Ticket, each with its resolved
     * data table and active fields. Optionally restricted to a set of ids
     * (the admin-allowed containers).
     *
     * @param int[]|null $onlyIds
     * @return array<int,array{id:int,name:string,label:string,dataTable:string,fields:array}>
     */
    public function containers(?array $onlyIds = null): array
    {
        if ($this->containerCache === null) {
            $this->containerCache = $this->loadContainers();
        }

        if ($onlyIds === null || $onlyIds === []) {
            return $this->containerCache;
        }
        $set = array_flip($onlyIds);
        return array_filter($this->containerCache, static fn($c) => isset($set[$c['id']]));
    }

    public function container(int $id): ?array
    {
        return $this->containers()[$id] ?? null;
    }

    /**
     * Lightweight list for pickers: id, label, active field count.
     *
     * @param int[]|null $onlyIds
     * @return array<int,array{id:int,label:string,name:string,fieldCount:int}>
     */
    public function containerOptions(?array $onlyIds = null): array
    {
        $out = [];
        foreach ($this->containers($onlyIds) as $c) {
            $out[] = [
                'id'         => $c['id'],
                'label'      => $c['label'],
                'name'       => $c['name'],
                'fieldCount' => count($c['fields']),
            ];
        }
        return $out;
    }

    private function loadContainers(): array
    {
        $db = $this->glpi->connection();

        $rows = $db->table('glpi_plugin_fields_containers')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $containers = [];
        foreach ($rows as $row) {
            $itemtypes = (string) ($row['itemtypes'] ?? '');
            if (stripos($itemtypes, 'Ticket') === false) {
                continue;
            }

            $id        = (int) $row['id'];
            $name      = (string) $row['name'];
            $dataTable = $this->resolveDataTable($db, $name);
            if ($dataTable === null) {
                log_message('warning', "[ServiceDesk] container #{$id} ({$name}) has no resolvable data table — skipped.");
                continue;
            }

            $tableCols = $db->getFieldNames($dataTable);
            $fields    = $this->loadFields($db, $id, $tableCols);
            if ($fields === []) {
                continue;
            }

            $containers[$id] = [
                'id'        => $id,
                'name'      => $name,
                'label'     => (string) ($row['label'] ?: $name),
                'dataTable' => $dataTable,
                'fields'    => $fields,
            ];
        }

        return $containers;
    }

    /**
     * Active fields of a container mapped to their physical columns.
     * Only fields whose derived column actually exists in the data table are kept.
     */
    private function loadFields(BaseConnection $db, int $containerId, array $tableCols): array
    {
        $rows = $db->table('glpi_plugin_fields_fields')
            ->where('plugin_fields_containers_id', $containerId)
            ->where('is_active', 1)
            ->orderBy('ranking', 'ASC')
            ->get()->getResultArray();

        $fields = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $type = (string) $row['type'];

            if ($type === 'dropdown') {
                $column        = 'plugin_fields_' . $name . self::DROPDOWN_SUFFIX . '_id';
                $dropdownTable = self::DROPDOWN_PREFIX . $name . self::DROPDOWN_SUFFIX;
            } else {
                $column        = $name;
                $dropdownTable = null;
            }

            // Defensive: skip fields whose physical column is not present.
            if (! in_array($column, $tableCols, true)) {
                log_message('warning', "[ServiceDesk] field '{$name}' -> column '{$column}' missing in data table — skipped.");
                continue;
            }

            $fields[] = [
                'name'          => $name,
                'label'         => (string) ($row['label'] ?: $name),
                'type'          => $type,
                'mandatory'     => (int) ($row['mandatory'] ?? 0) === 1,
                'column'        => $column,
                'dropdownTable' => $dropdownTable,
            ];
        }

        return $fields;
    }

    /**
     * Resolves the physical data table for a container by its name. The GLPI
     * Fields plugin pluralizes: glpi_plugin_fields_ticket<name>s. Falls back to
     * the unpluralized form, then gives up (logged).
     */
    private function resolveDataTable(BaseConnection $db, string $name): ?string
    {
        foreach ([self::DATA_PREFIX . $name . 's', self::DATA_PREFIX . $name] as $candidate) {
            if ($db->tableExists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Import plan (shared by template builder, validator, importer)
    // ------------------------------------------------------------------

    /**
     * Builds the column plan for a selection of containers. Base ticket columns
     * first, then each container's fields with UNIQUE Excel headers (a label
     * shared by two containers is suffixed with the container label).
     *
     * @param int[] $containerIds
     * @param bool  $withOptions  load dropdown/enum/catalog option lists (for the template)
     * @return array{containers:array<int,array>,columns:array<int,array<string,mixed>>}
     */
    public function buildPlan(array $containerIds, bool $withOptions = true): array
    {
        $selected = $this->containers($containerIds);
        $columns  = [];
        $usedHeaders = [];

        // Base ticket columns.
        foreach ($this->config->baseColumns() as $base) {
            $header = $this->uniqueHeader($base['header'], $usedHeaders);
            $columns[] = [
                'header'   => $header,
                'kind'     => 'base',
                'glpiKey'  => $base['glpiKey'],
                'type'     => $base['type'],
                'required' => $base['required'],
                'desc'     => $base['desc'],
                'example'  => $base['example'],
                'options'  => $withOptions ? $this->baseOptions($base) : [],
            ];
        }

        // Detect label collisions across selected containers to know when to suffix.
        $labelCount = [];
        foreach ($selected as $c) {
            foreach ($c['fields'] as $f) {
                $key = mb_strtoupper($f['label']);
                $labelCount[$key] = ($labelCount[$key] ?? 0) + 1;
            }
        }

        foreach ($selected as $c) {
            foreach ($c['fields'] as $f) {
                $baseHeader = mb_strtoupper($f['label']);
                if (($labelCount[$baseHeader] ?? 0) > 1) {
                    $baseHeader .= ' (' . mb_strtoupper($c['label']) . ')';
                }
                $header = $this->uniqueHeader($baseHeader, $usedHeaders);

                $columns[] = [
                    'header'        => $header,
                    'kind'          => 'plugin',
                    'containerId'   => $c['id'],
                    'containerLabel' => $c['label'],
                    'dataTable'     => $c['dataTable'],
                    'field'         => $f['name'],
                    'column'        => $f['column'],
                    'dropdownTable' => $f['dropdownTable'],
                    'type'          => $f['type'],
                    'required'      => $f['mandatory'],
                    'desc'          => $f['type'] === 'dropdown'
                        ? 'Valor del catálogo. Si no existe puede autocrearse (según configuración).'
                        : 'Campo del contenedor ' . $c['label'] . '.',
                    'example'       => '',
                    'options'       => $withOptions && $f['type'] === 'dropdown'
                        ? $this->dropdownOptions($f['dropdownTable'])
                        : [],
                ];
            }
        }

        // Output column filled by the importer.
        $columns[] = [
            'header'   => $this->config->ticketIdHeader,
            'kind'     => 'output',
            'type'     => 'text',
            'required' => false,
            'desc'     => 'ID del ticket creado en GLPI. NO LLENAR — lo completa el sistema.',
            'example'  => '',
            'options'  => [],
        ];

        return ['containers' => $selected, 'columns' => $columns];
    }

    private function uniqueHeader(string $header, array &$used): string
    {
        $header = mb_strtoupper(trim($header));
        $candidate = $header;
        $i = 2;
        while (isset($used[$candidate])) {
            $candidate = $header . ' ' . $i;
            $i++;
        }
        $used[$candidate] = true;
        return $candidate;
    }

    /**
     * Option list for a base column (enum labels, GLPI categories or users).
     * @return string[]
     */
    private function baseOptions(array $base): array
    {
        return match ($base['type']) {
            'enum'          => array_keys($this->config->{$base['enum']}),
            'glpi_category' => $this->categoryNames(),
            'glpi_user'     => $this->userNames(),
            default         => [],
        };
    }

    /** @return string[] dropdown value names, ordered. */
    public function dropdownOptions(?string $table): array
    {
        if ($table === null || ! preg_match('/^' . self::DROPDOWN_PREFIX . '[a-z0-9]+' . self::DROPDOWN_SUFFIX . '$/', $table)) {
            return [];
        }
        $db = $this->glpi->connection();
        if (! $db->tableExists($table)) {
            return [];
        }
        $order = in_array('completename', $db->getFieldNames($table), true) ? 'completename' : 'name';
        $rows  = $db->table($table)->select('name')->orderBy($order, 'ASC')->get()->getResultArray();
        return array_values(array_filter(array_map(static fn($r) => trim((string) $r['name']), $rows), static fn($v) => $v !== ''));
    }

    /**
     * All GLPI ITIL categories (id + complete name). For the admin mapping screen.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function categories(): array
    {
        $db = $this->glpi->connection();
        if (! $db->tableExists('glpi_itilcategories')) {
            return [];
        }
        $col  = in_array('completename', $db->getFieldNames('glpi_itilcategories'), true) ? 'completename' : 'name';
        $rows = $db->table('glpi_itilcategories')->select("id, {$col} AS n")->orderBy($col, 'ASC')->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['n']);
            if ($name !== '') {
                $out[] = ['id' => (int) $r['id'], 'name' => $name];
            }
        }
        return $out;
    }

    /**
     * ROOT ITIL categories only (no parent). For the backlog report's area
     * mapping, where each root is assigned to Administración / Operaciones.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function rootCategories(): array
    {
        $db = $this->glpi->connection();
        if (! $db->tableExists('glpi_itilcategories')) {
            return [];
        }
        $cols = $db->getFieldNames('glpi_itilcategories');
        $col  = in_array('completename', $cols, true) ? 'completename' : 'name';

        $builder = $db->table('glpi_itilcategories')->select("id, {$col} AS n");
        if (in_array('itilcategories_id', $cols, true)) {
            $builder->where('itilcategories_id', 0);
        }
        $rows = $builder->orderBy($col, 'ASC')->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['n']);
            if ($name !== '') {
                $out[] = ['id' => (int) $r['id'], 'name' => $name];
            }
        }
        return $out;
    }

    /**
     * Category names offered in the template: only the SuperAdmin-supported ones
     * when any are configured; otherwise all (safe default before config).
     *
     * @return string[]
     */
    public function categoryNames(): array
    {
        $all       = $this->categories();
        $supported = $this->categoryMap->supportedIds();

        if ($supported !== []) {
            $set = array_flip($supported);
            $all = array_filter($all, static fn($c) => isset($set[$c['id']]));
        }

        return array_values(array_map(static fn($c) => $c['name'], $all));
    }

    /** @return string[] GLPI active users as "Realname Firstname". */
    public function userNames(): array
    {
        $db = $this->glpi->connection();
        if (! $db->tableExists('glpi_users')) {
            return [];
        }
        $rows = $db->table('glpi_users')
            ->select('realname, firstname')
            ->where('is_active', 1)
            ->where("realname !=", '')
            ->orderBy('realname', 'ASC')
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $name = trim(trim((string) $r['realname']) . ' ' . trim((string) $r['firstname']));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }
}
