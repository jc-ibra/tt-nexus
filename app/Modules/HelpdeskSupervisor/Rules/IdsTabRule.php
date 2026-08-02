<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * IDS tab (Manual Parte 3.7.5; complements KPI 3). The IDS tab (Nombre + Número
 * de empleado) must be filled in every category EXCEPT Control de Activos. Both
 * are mandatory dropdowns and do not accept the missing-data conventions.
 */
class IdsTabRule extends AbstractRule
{
    /** Candidate labels for the two IDS fields (first present wins). */
    private const FIELDS = [
        ['IDS Nombre', 'IDS - Nombre', 'Nombre IDS', 'IDS'],
        ['IDS Número de empleado', 'IDS Numero de empleado', 'Número de empleado', 'Numero de empleado'],
    ];

    public function key(): string { return 'ids_tab'; }
    public function name(): string { return 'Tab IDS incompleta'; }
    public function manualReference(): string { return 'Parte 3.7.5 - Tab IDS'; }
    public function kpiMapping(): ?string { return 'KPI-3'; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $c = $this->classifier->classify((string) $ticket['category_name']);
        if ($c['outOfScope'] || ! $c['requiresIds']) {
            return []; // Control de Activos and out-of-scope categories skip IDS
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
