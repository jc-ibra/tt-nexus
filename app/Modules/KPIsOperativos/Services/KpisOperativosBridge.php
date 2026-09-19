<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Models\GlpiCoordinatorModel;
use App\Modules\KPIsOperativos\Models\GlpiIdcCanonicalModel;

/**
 * Read-mostly bridge over the legacy KPIsOperativos catalogs, consumed by the
 * Reports module so the new GLPI-direct pipeline shares the same zone ->
 * coordinator/manager mapping and the same fuzzy-homologated technician
 * (IDC) catalog as the historical CSV reports, instead of duplicating them.
 *
 * Mirrors the role HelpdeskSupervisorBridge/AgentKpisBridge play for their
 * modules: other modules go through this service, never the tables directly.
 */
class KpisOperativosBridge
{
    public function __construct(
        private GlpiCoordinatorModel $coordinators,
        private GlpiIdcCanonicalModel $canonicals,
        private GlpiIdcHomologator $homologator,
    ) {}

    /**
     * Zone (normalized) -> coordinator/manager, same lookup GlpiKpiCalculator
     * uses to build coord_info.
     *
     * @return array<string,array{coord:string,gte:string,raw_zone:string}>
     */
    public function coordinatorMap(): array
    {
        return $this->coordinators->getNormalizedMap();
    }

    public static function normalizeZone(string $zone): string
    {
        return GlpiCoordinatorModel::normalizeZone($zone);
    }

    /**
     * Resolves a raw IDC/technician name (as it comes from GLPI) to its
     * canonical display name, homologating fuzzy variants the same way the
     * legacy CSV pipeline does. Creates a new canonical entry the first time
     * a genuinely new name is seen — that catalog is meant to be shared and
     * grown by both sources, not frozen per source.
     *
     * Returns null for empty/"SIN ASIGNAR" input, same convention as the
     * legacy homologator.
     */
    public function canonicalIdcName(string $rawIdc): ?string
    {
        $id = $this->homologator->resolve($rawIdc);
        if ($id === null) {
            return null;
        }
        $row = $this->canonicals->find($id);
        return $row ? (string) $row['canonical_name'] : null;
    }
}
