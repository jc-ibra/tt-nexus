<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Config\GlpiCatalogs;
use App\Modules\Provisioning\Models\GlpiCatalogPrefModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Generic CRUD over GLPI's "additional fields" dropdown catalogs
 * (glpi_plugin_fields_<slug>dropdowns). These are GLPI CommonTreeDropdown
 * tables, so writes must keep the tree columns consistent (completename,
 * level, ancestors_cache, sons_cache) — handled by rebuildTree().
 *
 * Table names are ALWAYS resolved against the live discovery whitelist; raw
 * user input never reaches a query as an identifier.
 */
class GlpiCatalogService
{
    private const TABLE_PREFIX = 'glpi_plugin_fields_';
    private const TABLE_SUFFIX = 'dropdowns';
    private const SLUG_RE       = '/^[a-z0-9]+$/';
    private const SEP           = ' > ';

    /** @var array<string,array>|null cache of columns per table */
    private array $colCache = [];
    /** @var string[]|null cached discovery list */
    private ?array $discovered = null;

    public function __construct(
        private GlpiDbConnection $glpi,
        private GlpiCatalogs $config,
        private GlpiCatalogPrefModel $prefs,
    ) {}

    public function isConfigured(): bool
    {
        return $this->glpi->isConfigured();
    }

    // ------------------------------------------------------------------
    // Discovery + registry merge
    // ------------------------------------------------------------------

    /**
     * All dropdown tables grouped by tab, with friendly label and row count.
     *
     * @return array<string, array<int, array{slug:string,table:string,label:string,count:int}>>
     */
    public function listCatalogs(): array
    {
        $db     = $this->glpi->connection();
        $tables = $this->discover($db);
        $prefs  = $this->prefs->all();

        $grouped = [];
        foreach ($tables as $table) {
            $pref = $prefs[$table] ?? null;

            // Nexus-only visibility: hidden catalogs never show in the UI.
            if ($pref !== null && (int) $pref['is_hidden'] === 1) {
                continue;
            }

            $label = ($pref['label'] ?? '') !== '' ? $pref['label'] : ($this->config->registry[$table]['label'] ?? $this->deriveLabel($table));
            $tabs  = ($pref['classification'] ?? '') !== ''
                ? [$pref['classification']]
                : ($this->config->registry[$table]['tabs'] ?? [$this->config->unclassifiedTab]);

            $entry = [
                'slug'  => $this->slugOf($table),
                'table' => $table,
                'label' => $label,
                'count' => (int) $db->table($table)->countAllResults(),
            ];
            foreach ($tabs as $tab) {
                $grouped[$tab][] = $entry;
            }
        }

        // Order tabs by config preference, then any extras alphabetically.
        $ordered = [];
        foreach ($this->config->tabOrder as $tab) {
            if (isset($grouped[$tab])) {
                $ordered[$tab] = $grouped[$tab];
            }
        }
        foreach ($grouped as $tab => $items) {
            if (! isset($ordered[$tab])) {
                $ordered[$tab] = $items;
            }
        }

        return $ordered;
    }

    /**
     * Resolves a URL slug to a validated, whitelisted table name.
     */
    public function resolveTable(string $slug): ?string
    {
        if (! preg_match(self::SLUG_RE, $slug)) {
            return null;
        }
        $table = self::TABLE_PREFIX . $slug . self::TABLE_SUFFIX;

        return in_array($table, $this->discover($this->glpi->connection()), true) ? $table : null;
    }

    public function labelFor(string $table): string
    {
        $pref = $this->prefs->all()[$table] ?? null;
        if ($pref !== null && ($pref['label'] ?? '') !== '') {
            return (string) $pref['label'];
        }
        return $this->config->registry[$table]['label'] ?? $this->deriveLabel($table);
    }

    /**
     * Every discovered catalog (INCLUDING hidden ones) with its effective label,
     * classification and hidden flag, for the Nexus-only management screen.
     *
     * @return array{rows: array<int, array<string,mixed>>, classifications: string[]}
     */
    public function listForManagement(): array
    {
        $db     = $this->glpi->connection();
        $tables = $this->discover($db);
        $prefs  = $this->prefs->all();

        $classifications = [];
        foreach ($this->config->tabOrder as $t) {
            $classifications[$t] = true;
        }

        $rows = [];
        foreach ($tables as $table) {
            $pref     = $prefs[$table] ?? null;
            $default  = $this->defaultClassification($table);
            $effClass = ($pref['classification'] ?? '') !== '' ? (string) $pref['classification'] : $default;
            $classifications[$effClass] = true;

            $rows[] = [
                'table'          => $table,
                'slug'           => $this->slugOf($table),
                'defaultLabel'   => $this->config->registry[$table]['label'] ?? $this->deriveLabel($table),
                'label'          => (string) ($pref['label'] ?? ''),
                'classification' => $effClass,
                'is_hidden'      => (int) ($pref['is_hidden'] ?? 0) === 1,
                'count'          => (int) $db->table($table)->countAllResults(),
            ];
        }

        return ['rows' => $rows, 'classifications' => array_keys($classifications)];
    }

    /**
     * Saves a Nexus-only preference for one catalog. When the values match the
     * static defaults (no custom label, default classification, not hidden) the
     * row is removed so registry defaults keep flowing.
     */
    public function savePref(string $table, string $label, string $classification, bool $hidden): ServiceResult
    {
        if ($this->resolveTable($this->slugOf($table)) !== $table) {
            return ServiceResult::fail('Catálogo no válido.');
        }

        $default = $this->defaultClassification($table);
        $isDefault = $label === '' && ! $hidden && ($classification === '' || $classification === $default);

        if ($isDefault) {
            $this->prefs->delete($table);
        } else {
            $this->prefs->upsert($table, $label, $classification !== '' ? $classification : $default, $hidden);
        }

        return ServiceResult::ok(null, 'Preferencias guardadas.');
    }

    private function defaultClassification(string $table): string
    {
        return $this->config->registry[$table]['tabs'][0] ?? $this->config->unclassifiedTab;
    }

    // ------------------------------------------------------------------
    // Values CRUD
    // ------------------------------------------------------------------

    /**
     * All values (no pagination). Used by parentOptions/findValue.
     *
     * @return array<int, array{id:int,name:string,completename:string,comment:string,parent:int,level:int}>
     */
    public function listValues(string $table): array
    {
        $db   = $this->glpi->connection();
        $cols = $this->columns($db, $table);
        $pcol = $this->parentColumn($table);

        $rows = $db->table($table)
            ->select(implode(', ', $this->valueSelect($cols, $pcol)))
            ->orderBy($this->valueOrder($cols), 'ASC')
            ->get()->getResultArray();

        return $this->mapValueRows($rows);
    }

    /**
     * One page of values, ordered by completename. For large catalogs (1000+).
     *
     * @return array{values: array<int,array<string,mixed>>, total:int, page:int, perPage:int, lastPage:int}
     */
    public function paginateValues(string $table, int $page, int $perPage): array
    {
        $db      = $this->glpi->connection();
        $cols    = $this->columns($db, $table);
        $pcol    = $this->parentColumn($table);
        $perPage = max(1, $perPage);

        $builder = $db->table($table);
        $total   = $builder->countAllResults(false);

        $page    = max(1, min($page, (int) max(1, ceil($total / $perPage))));
        $offset  = ($page - 1) * $perPage;

        $rows = $builder
            ->select(implode(', ', $this->valueSelect($cols, $pcol)))
            ->orderBy($this->valueOrder($cols), 'ASC')
            ->get($perPage, $offset)
            ->getResultArray();

        return [
            'values'   => $this->mapValueRows($rows),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /** @return string[] */
    private function valueSelect(array $cols, string $pcol): array
    {
        $select = ['id', 'name'];
        foreach (['completename', 'comment', 'level'] as $c) {
            if (in_array($c, $cols, true)) {
                $select[] = $c;
            }
        }
        if (in_array($pcol, $cols, true)) {
            $select[] = "$pcol AS parent";
        }
        return $select;
    }

    private function valueOrder(array $cols): string
    {
        return in_array('completename', $cols, true) ? 'completename' : 'name';
    }

    /** @return array<int,array<string,mixed>> */
    private function mapValueRows(array $rows): array
    {
        return array_map(static fn($r) => [
            'id'           => (int) $r['id'],
            'name'         => (string) ($r['name'] ?? ''),
            'completename' => (string) ($r['completename'] ?? ($r['name'] ?? '')),
            'comment'      => (string) ($r['comment'] ?? ''),
            'parent'       => (int) ($r['parent'] ?? 0),
            'level'        => (int) ($r['level'] ?? 1),
        ], $rows);
    }

    public function findValue(string $table, int $id): ?array
    {
        foreach ($this->listValues($table) as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /** Options usable as parent for a tree dropdown (id => completename). */
    public function parentOptions(string $table, ?int $excludeId = null): array
    {
        $out = [];
        foreach ($this->listValues($table) as $row) {
            if ($excludeId !== null && $row['id'] === $excludeId) {
                continue;
            }
            $out[$row['id']] = $row['completename'];
        }
        return $out;
    }

    public function createValue(string $table, array $data): ServiceResult
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ServiceResult::fail('El nombre es obligatorio.');
        }

        $db      = $this->glpi->connection();
        $cols    = $this->columns($db, $table);
        $pcol    = $this->parentColumn($table);
        $parent  = (int) ($data['parent'] ?? 0);
        $isTree  = in_array('completename', $cols, true);

        if ($parent > 0 && ! $this->rowExists($db, $table, $parent)) {
            return ServiceResult::fail('El elemento padre seleccionado no existe.');
        }

        $insert = ['name' => $name];
        if (in_array('comment', $cols, true)) {
            $insert['comment'] = trim((string) ($data['comment'] ?? ''));
        }
        if (in_array('entities_id', $cols, true)) {
            $insert['entities_id'] = 0;
        }
        if (in_array('is_recursive', $cols, true)) {
            $insert['is_recursive'] = 0;
        }
        if ($isTree) {
            $insert[$pcol]         = $parent;
            $insert['level']       = 1;
            $insert['completename'] = $name;
        }
        if (in_array('date_creation', $cols, true)) {
            $insert['date_creation'] = date('Y-m-d H:i:s');
        }
        if (in_array('date_mod', $cols, true)) {
            $insert['date_mod'] = date('Y-m-d H:i:s');
        }

        $db->table($table)->insert($insert);
        $newId = (int) $db->insertID(); // capture BEFORE rebuildTree runs UPDATE queries

        if ($isTree) {
            $this->rebuildTree($db, $table);
        }

        log_message('info', "[GlpiCatalog] created value in {$table} by user_id=" . session()->get('user_id'));
        return ServiceResult::ok(['id' => $newId], 'Valor agregado correctamente.');
    }

    /**
     * Finds a value by name (case-insensitive, trimmed) within a catalog table.
     * Used for duplicate detection before an automated insert.
     */
    public function findByName(string $table, string $name): ?array
    {
        $needle = mb_strtolower(trim($name), 'UTF-8');
        if ($needle === '') {
            return null;
        }
        foreach ($this->listValues($table) as $row) {
            if (mb_strtolower(trim($row['name']), 'UTF-8') === $needle) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Idempotent insert: creates the value only when no entry with the same
     * name already exists. On success, data['existed'] is true when the value
     * was already present (skipped) and false when it was freshly created.
     */
    public function ensureValue(string $table, string $name, string $comment = ''): ServiceResult
    {
        $name = trim($name);
        if ($name === '') {
            return ServiceResult::fail('El nombre es obligatorio.');
        }

        $existing = $this->findByName($table, $name);
        if ($existing !== null) {
            return ServiceResult::ok(['id' => (int) $existing['id'], 'existed' => true], 'El valor ya existía.');
        }

        $result = $this->createValue($table, ['name' => $name, 'comment' => $comment]);
        if (! $result->success) {
            return $result;
        }

        return ServiceResult::ok(['id' => (int) ($result->data['id'] ?? 0), 'existed' => false], $result->message);
    }

    public function updateValue(string $table, int $id, array $data): ServiceResult
    {
        $db   = $this->glpi->connection();
        if (! $this->rowExists($db, $table, $id)) {
            return ServiceResult::fail('El valor no existe.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ServiceResult::fail('El nombre es obligatorio.');
        }

        $cols   = $this->columns($db, $table);
        $pcol   = $this->parentColumn($table);
        $isTree = in_array('completename', $cols, true);

        $update = ['name' => $name];
        if (in_array('comment', $cols, true)) {
            $update['comment'] = trim((string) ($data['comment'] ?? ''));
        }
        if (in_array('date_mod', $cols, true)) {
            $update['date_mod'] = date('Y-m-d H:i:s');
        }

        if ($isTree && array_key_exists('parent', $data)) {
            $parent = (int) $data['parent'];
            if ($parent === $id) {
                return ServiceResult::fail('Un valor no puede ser su propio padre.');
            }
            if ($parent > 0) {
                if (! $this->rowExists($db, $table, $parent)) {
                    return ServiceResult::fail('El elemento padre seleccionado no existe.');
                }
                if ($this->isDescendant($db, $table, $parent, $id)) {
                    return ServiceResult::fail('No se puede mover un valor dentro de uno de sus descendientes.');
                }
            }
            $update[$pcol] = $parent;
        }

        $db->table($table)->where('id', $id)->update($update);

        if ($isTree) {
            $this->rebuildTree($db, $table);
        }

        log_message('info', "[GlpiCatalog] updated value #{$id} in {$table} by user_id=" . session()->get('user_id'));
        return ServiceResult::ok(null, 'Valor actualizado correctamente.');
    }

    /**
     * Bulk-imports values at root level (no hierarchy). Skips empty names and
     * names that already exist (case-insensitive). One rebuildTree at the end.
     *
     * @param array<int,array{name:string,comment?:string}> $rows
     */
    public function importValues(string $table, array $rows): ServiceResult
    {
        $db     = $this->glpi->connection();
        $cols   = $this->columns($db, $table);
        $pcol   = $this->parentColumn($table);
        $isTree = in_array('completename', $cols, true);
        $now    = date('Y-m-d H:i:s');

        // Existing names (lowercased) so we don't create duplicates.
        $existing = [];
        foreach ($db->table($table)->select('name')->get()->getResultArray() as $r) {
            $existing[mb_strtolower(trim((string) $r['name']))] = true;
        }

        $created = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($existing[$key])) {
                $skipped++;
                continue;
            }

            $insert = ['name' => $name];
            if (in_array('comment', $cols, true)) {
                $insert['comment'] = trim((string) ($row['comment'] ?? ''));
            }
            if (in_array('entities_id', $cols, true)) {
                $insert['entities_id'] = 0;
            }
            if (in_array('is_recursive', $cols, true)) {
                $insert['is_recursive'] = 0;
            }
            if ($isTree) {
                $insert[$pcol]          = 0;
                $insert['level']        = 1;
                $insert['completename'] = $name;
            }
            if (in_array('date_creation', $cols, true)) {
                $insert['date_creation'] = $now;
            }
            if (in_array('date_mod', $cols, true)) {
                $insert['date_mod'] = $now;
            }

            $db->table($table)->insert($insert);
            $existing[$key] = true;
            $created++;
        }

        if ($created > 0 && $isTree) {
            $this->rebuildTree($db, $table);
        }

        log_message('info', "[GlpiCatalog] imported {$created} values into {$table} by user_id=" . session()->get('user_id'));

        if ($created === 0) {
            return ServiceResult::fail("No se importó ningún valor (se omitieron {$skipped}: vacíos o ya existentes).");
        }
        return ServiceResult::ok(
            ['created' => $created, 'skipped' => $skipped],
            "Importación completa: {$created} agregados, {$skipped} omitidos (vacíos o duplicados).",
        );
    }

    public function deleteValue(string $table, int $id): ServiceResult
    {
        $db   = $this->glpi->connection();
        if (! $this->rowExists($db, $table, $id)) {
            return ServiceResult::fail('El valor no existe.');
        }

        $cols = $this->columns($db, $table);
        $pcol = $this->parentColumn($table);
        $isTree = in_array('completename', $cols, true);

        if ($isTree) {
            $children = (int) $db->table($table)->where($pcol, $id)->countAllResults();
            if ($children > 0) {
                return ServiceResult::fail('No se puede eliminar: el valor tiene elementos hijos. Elimina o reasigna los hijos primero.');
            }
        }

        $db->table($table)->where('id', $id)->delete();

        if ($isTree) {
            $this->rebuildTree($db, $table);
        }

        log_message('info', "[GlpiCatalog] deleted value #{$id} in {$table} by user_id=" . session()->get('user_id'));
        return ServiceResult::ok(null, 'Valor eliminado correctamente.');
    }

    // ------------------------------------------------------------------
    // Tree maintenance (GLPI CommonTreeDropdown caches)
    // ------------------------------------------------------------------

    /**
     * Recomputes completename, level, ancestors_cache and sons_cache for every
     * row of a tree dropdown. Cheap: these catalogs hold few rows.
     */
    private function rebuildTree(BaseConnection $db, string $table): void
    {
        $pcol = $this->parentColumn($table);
        $rows = $db->table($table)->select("id, name, {$pcol} AS parent")->get()->getResultArray();

        /** @var array<int,array{name:string,parent:int}> $byId */
        $byId     = [];
        $children = [];
        foreach ($rows as $r) {
            $id           = (int) $r['id'];
            $parent       = (int) $r['parent'];
            $byId[$id]    = ['name' => (string) $r['name'], 'parent' => $parent];
            $children[$parent][] = $id;
        }

        $batch = [];
        foreach ($byId as $id => $node) {
            // Ancestors (root -> parent), with cycle/missing-parent guard.
            $ancestors = [];
            $seen      = [];
            $cursor    = $node['parent'];
            while ($cursor > 0 && isset($byId[$cursor]) && ! isset($seen[$cursor])) {
                $seen[$cursor]      = true;
                $ancestors[]        = $cursor;
                $cursor             = $byId[$cursor]['parent'];
            }
            $ancestors = array_reverse($ancestors); // root first

            // completename = names from root down to self joined by " > ".
            $names = [];
            foreach ($ancestors as $aid) {
                $names[] = $byId[$aid]['name'];
            }
            $names[]      = $node['name'];
            $completename = implode(self::SEP, $names);

            // Caches: GLPI stores id=>id maps (empty array serializes to "[]").
            $ancestorsMap = [];
            foreach ($ancestors as $aid) {
                $ancestorsMap[$aid] = $aid;
            }
            $sonsMap = [$id => $id] + $this->collectDescendants($children, $id);

            $batch[] = [
                'id'              => $id,
                'completename'    => $completename,
                'level'           => count($ancestors) + 1,
                'ancestors_cache' => json_encode($ancestorsMap),
                'sons_cache'      => json_encode($sonsMap),
            ];
        }

        if ($batch !== []) {
            $db->table($table)->updateBatch($batch, 'id');
        }
    }

    /** @return array<int,int> descendantId => descendantId (excludes $id) */
    private function collectDescendants(array $children, int $id): array
    {
        $out   = [];
        $stack = $children[$id] ?? [];
        while ($stack !== []) {
            $cur = array_pop($stack);
            if (isset($out[$cur])) {
                continue;
            }
            $out[$cur] = $cur;
            foreach ($children[$cur] ?? [] as $child) {
                $stack[] = $child;
            }
        }
        return $out;
    }

    private function isDescendant(BaseConnection $db, string $table, int $candidate, int $ancestor): bool
    {
        $pcol   = $this->parentColumn($table);
        $rows   = $db->table($table)->select("id, {$pcol} AS parent")->get()->getResultArray();
        $parents = [];
        foreach ($rows as $r) {
            $parents[(int) $r['id']] = (int) $r['parent'];
        }
        $cursor = $candidate;
        $seen   = [];
        while ($cursor > 0 && isset($parents[$cursor]) && ! isset($seen[$cursor])) {
            if ($parents[$cursor] === $ancestor) {
                return true;
            }
            $seen[$cursor] = true;
            $cursor        = $parents[$cursor];
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return string[] list of dropdown table names in the GLPI database */
    private function discover(BaseConnection $db): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }
        $database = $db->getDatabase();
        $rows = $db->table('information_schema.TABLES')
            ->select('TABLE_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->like('TABLE_NAME', self::TABLE_PREFIX, 'after')
            ->like('TABLE_NAME', self::TABLE_SUFFIX, 'before')
            ->orderBy('TABLE_NAME', 'ASC')
            ->get()->getResultArray();

        $tables = [];
        foreach ($rows as $r) {
            $name = (string) ($r['TABLE_NAME'] ?? $r['table_name'] ?? '');
            // Enforce the exact shape so the derived slug is safe.
            if ($name !== '' && preg_match('/^' . preg_quote(self::TABLE_PREFIX, '/') . '[a-z0-9]+' . self::TABLE_SUFFIX . '$/', $name)) {
                $tables[] = $name;
            }
        }

        return $this->discovered = $tables;
    }

    private function columns(BaseConnection $db, string $table): array
    {
        if (! isset($this->colCache[$table])) {
            $this->colCache[$table] = $db->getFieldNames($table);
        }
        return $this->colCache[$table];
    }

    private function rowExists(BaseConnection $db, string $table, int $id): bool
    {
        return $id > 0 && $db->table($table)->where('id', $id)->countAllResults() > 0;
    }

    private function parentColumn(string $table): string
    {
        // glpi_plugin_fields_regionalfielddropdowns -> plugin_fields_regionalfielddropdowns_id
        return substr($table, strlen('glpi_')) . '_id';
    }

    private function slugOf(string $table): string
    {
        $slug = substr($table, strlen(self::TABLE_PREFIX));
        return substr($slug, 0, -strlen(self::TABLE_SUFFIX));
    }

    private function deriveLabel(string $table): string
    {
        // regionalfield -> "Regionalfield"; best-effort for unclassified tables.
        $slug = $this->slugOf($table);
        $slug = preg_replace('/field$/', '', $slug) ?? $slug;
        return ucfirst($slug);
    }
}
