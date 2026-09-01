<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

use App\Modules\HelpdeskSupervisor\Rules\Support\CategoryClassifier;
use App\Modules\HelpdeskSupervisor\Services\IdsTabScopeService;

/**
 * IDS tab (Manual Parte 3.7.5; complements KPI 3). The IDS tab (Nombre + Número
 * de empleado) must be filled in categories that require it. Scope comes from
 * Service Desk → Categorías (audit_ids_tab) when configured; otherwise the
 * built-in classifier (Control de Activos / Control de Envíos excluded).
 */
class IdsTabRule extends AbstractRule
{
    /** Candidate labels for the two IDS fields (first present wins). */
    private const FIELDS = [
        ['IDS Nombre', 'IDS - Nombre', 'Nombre IDS', 'IDS'],
        ['IDS Número de empleado', 'IDS Numero de empleado', 'Número de empleado', 'Numero de empleado'],
    ];

    public function __construct(
        ?CategoryClassifier $classifier = null,
        private ?IdsTabScopeService $idsScope = null,
    ) {
        parent::__construct($classifier);
        $this->idsScope ??= service('helpdeskIdsTabScope');
    }

    public function key(): string { return 'ids_tab'; }
    public function name(): string { return 'Tab IDS incompleta'; }
    public function manualReference(): string { return 'Parte 3.7.5 - Tab IDS'; }
    public function kpiMapping(): ?string { return 'KPI-3'; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $c = $this->classifier->classify((string) $ticket['category_name']);
        if (! $this->idsScope->requiresIdsTab((int) ($ticket['itilcategories_id'] ?? 0), $c)) {
            return [];
        }

        $containerId = $ctx->tabContainerId('ids');
        if ($containerId === 0) {
            return [];
        }

        $fieldsByLabel = $ctx->fieldsByLabel($containerId);
        $out = [];
        foreach (self::FIELDS as $candidates) {
            $label = null;
            foreach ($candidates as $cand) {
                if (isset($fieldsByLabel[mb_strtolower($cand)])) {
                    $label = $cand;
                    break;
                }
            }
            if ($label === null) {
                continue;
            }
            if ($ctx->pluginIsEmpty($ticket, $containerId, $label)) {
                $out[] = $this->deviation(
                    "El campo IDS «{$label}» está vacío; es obligatorio en esta categoría.",
                    $label, 'Seleccionado de la lista', '(vacío)',
                );
            }
        }

        return $out;
    }
}
