<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;

/**
 * Decides whether the IDS tab audit rule applies to a ticket category.
 * Uses the Service Desk category map when configured; otherwise the classifier.
 */
class IdsTabScopeService
{
    /** @var array<int,int>|null category id => parent id */
    private ?array $parents = null;

    /** @var int[]|null */
    private ?array $scopeRootIds = null;

    public function __construct(
        private ServiceDeskCategoryMapModel $categoryMap,
        private GlpiDbConnection $glpi,
    ) {}

    /**
     * @param array{outOfScope:bool,requiresIds:bool} $classification
     */
    public function requiresIdsTab(int $categoryId, array $classification): bool
    {
        if ($classification['outOfScope']) {
            return false;
        }

        $roots = $this->scopeRootIds();
        if ($roots === []) {
            return (bool) $classification['requiresIds'];
        }

        return $this->isInSubtree($categoryId, $roots);
    }

    /** @return int[] */
    private function scopeRootIds(): array
    {
        if ($this->scopeRootIds === null) {
            $this->scopeRootIds = $this->categoryMap->idsTabScopeIds();
        }

        return $this->scopeRootIds;
    }

    /** @param int[] $rootIds */
    private function isInSubtree(int $categoryId, array $rootIds): bool
    {
        if ($categoryId <= 0) {
            return false;
        }

        $parents = $this->categoryParents();
        $seen    = [];
        $cur     = $categoryId;
        while ($cur > 0 && ! isset($seen[$cur])) {
            if (in_array($cur, $rootIds, true)) {
                return true;
            }
            $seen[$cur] = true;
            $cur        = $parents[$cur] ?? 0;
        }

        return false;
    }

    /** @return array<int,int> */
    private function categoryParents(): array
    {
        if ($this->parents !== null) {
            return $this->parents;
        }

        $this->parents = [];
        if (! $this->glpi->isConfigured()) {
            return $this->parents;
        }

        $db = $this->glpi->connection();
        if (! $db->tableExists('glpi_itilcategories')) {
            return $this->parents;
        }

        $cols = $db->getFieldNames('glpi_itilcategories');
        if (! in_array('itilcategories_id', $cols, true)) {
            return $this->parents;
        }

        foreach ($db->table('glpi_itilcategories')->select('id, itilcategories_id')->get()->getResultArray() as $row) {
            $this->parents[(int) $row['id']] = (int) ($row['itilcategories_id'] ?? 0);
        }

        return $this->parents;
    }
}
