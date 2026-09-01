<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Shared GLPI ticket filters for Resumen GLPI and audit/KPI ticket selection.
 * Keeps overview aggregates and agent denominators on the same scope.
 */
class GlpiTicketScope
{
    /** GLPI glpi_tickets_users.type for the requester (solicitante). */
    public const LINK_REQUESTER = 1;

    /**
     * @param int[]|null $statusFilter null = any status
     * @param int[]      $types        empty = no type filter
     * @param int[]|null $entityIds    null = all entities
     * @param int[]|null $categoryIds  null = all categories
     */
    public static function apply(
        BaseBuilder $builder,
        ?array $statusFilter,
        array $types,
        ?array $entityIds,
        ?array $categoryIds,
        ?string $periodStart,
        ?string $periodEnd,
        string $alias = '',
    ): void {
        $p = $alias !== '' ? $alias . '.' : '';

        if ($statusFilter !== null) {
            if ($statusFilter === []) {
                $builder->where($p . 'id', 0)->where($p . 'id !=', 0);
            } else {
                $builder->whereIn($p . 'status', $statusFilter);
            }
        }

        if ($types !== []) {
            $builder->whereIn($p . 'type', $types);
        }
        if ($entityIds !== null) {
            if ($entityIds === []) {
                $builder->where($p . 'id', 0)->where($p . 'id !=', 0);
            } else {
                $builder->whereIn($p . 'entities_id', $entityIds);
            }
        }
        if ($categoryIds !== null) {
            if ($categoryIds === []) {
                $builder->where($p . 'id', 0)->where($p . 'id !=', 0);
            } else {
                $builder->whereIn($p . 'itilcategories_id', $categoryIds);
            }
        }
        if ($periodStart !== null && $periodEnd !== null) {
            $builder->where($p . 'date >=', $periodStart . ' 00:00:00');
            $builder->where($p . 'date <=', $periodEnd . ' 23:59:59');
        }
    }

    /** @return int[]|null */
    public static function resolveEntityIds(BaseConnection $db, HelpdeskSupervisorSettings $settings): ?array
    {
        if ($settings->overviewEntitiesMode() !== 'specific') {
            return null;
        }
        $root = $settings->overviewEntitiesId();
        if (! $settings->overviewEntitiesRecursive()) {
            return [$root];
        }

        return self::entitySubtreeIds($db, $root);
    }

    /** @return int[]|null */
    public static function resolveCategoryScopeIds(BaseConnection $db, HelpdeskSupervisorSettings $settings): ?array
    {
        $roots = $settings->overviewCategoryRoots();
        if ($roots === []) {
            return null;
        }

        return self::categorySubtreeIds($db, $roots);
    }

    /** @return int[] */
    private static function entitySubtreeIds(BaseConnection $db, int $rootId): array
    {
        if (! $db->tableExists('glpi_entities')) {
            return [$rootId];
        }
        $rows = $db->table('glpi_entities')->select('id, entities_id')->get()->getResultArray();
        $children = [];
        foreach ($rows as $r) {
            $children[(int) $r['entities_id']][] = (int) $r['id'];
        }
        $out = [];
        $queue = [$rootId];
        $seen = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $out !== [] ? $out : [$rootId];
    }

    /**
     * @param int[] $rootIds
     * @return int[]
     */
    private static function categorySubtreeIds(BaseConnection $db, array $rootIds): array
    {
        if (! $db->tableExists('glpi_itilcategories')) {
            return $rootIds;
        }
        $cols = $db->getFieldNames('glpi_itilcategories');
        if (! in_array('itilcategories_id', $cols, true)) {
            return $rootIds;
        }
        $rows = $db->table('glpi_itilcategories')->select('id, itilcategories_id')->get()->getResultArray();
        $children = [];
        foreach ($rows as $r) {
            $children[(int) $r['itilcategories_id']][] = (int) $r['id'];
        }
        $out = [];
        $queue = $rootIds;
        $seen = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $out;
    }
}
