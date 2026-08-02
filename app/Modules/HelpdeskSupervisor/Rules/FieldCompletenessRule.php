<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Field completeness (Manual Parte 3.7; KPI 3). Verifies the mandatory fields of
 * the tab that corresponds to the ticket's category are filled. The convention
 * NO PROPORCIONADO counts as filled (non-empty); a truly empty value does not.
 *
 * Only evaluates a tab when its container is mapped in settings; unmapped tabs
 * and fields whose label is absent in the live schema are skipped (safe: no
 * false positives, only missed detections that config/label tuning resolves).
 */
class FieldCompletenessRule extends AbstractRule
{
    /** Required fields per tab, each with candidate labels (first present wins). */
    private const REQUIRED = [
        'clientes_externos' => [
            ['Regional'], ['Estado'], ['Municipio'],
            ['Local o Foráneo', 'Local/Foráneo', 'Local o Foraneo'],
            ['Usuario'], ['Equipo'], ['Modelo'], ['Serie'],
            ['CC', 'Centro de costos', 'Centro de Costos'],
        ],
        'areas_internas' => [
            ['Equipo'],
        ],
        'control_activos' => [
            ['Equipo'], ['Modelo'], ['Serie'],
        ],
        'control_envios' => [
            ['Guía', 'Guia'], ['Carrier'], ['Proyecto'], ['Solicitante'],
            ['CC', 'Centro de costos'], ['Remitente Nombre'], ['Remitente Estado'],
            ['Remitente Localidad'], ['Destinatario Nombre'], ['Destinatario Estado'],
            ['Destinatario Localidad'], ['Fecha de envío', 'Fecha de envio'],
        ],
    ];

    public function key(): string { return 'field_completeness'; }
    public function name(): string { return 'Completitud de campos'; }
    public function manualReference(): string { return 'Parte 3.7 - Campos personalizados'; }
    public function kpiMapping(): ?string { return 'KPI-3'; }
    public function severity(): string { return 'critical'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $c = $this->classifier->classify((string) $ticket['category_name']);
        if ($c['outOfScope']) {
            return [];
        }

        $out = [];

        // Base ticket checks.
        if (trim((string) $ticket['name']) === '') {
            $out[] = $this->deviation('El ticket no tiene título.', 'Título');
        }
        if ((int) $ticket['itilcategories_id'] === 0) {
            $out[] = $this->deviation('El ticket no tiene categoría asignada.', 'Categoría');
        }

        $tab = (string) $c['tab'];
        $required = self::REQUIRED[$tab] ?? [];
        $containerId = $ctx->tabContainerId($tab);
        if ($required === [] || $containerId === 0) {
            return $out; // tab not mapped or has no required set
        }

        // If the container has no data row for this ticket, the tab was not filled.
        if (! isset($ticket['plugin'][$containerId])) {
            $out[] = $this->deviation(
                "La tab correspondiente ({$tab}) no tiene datos capturados.",
                'Tab ' . $tab,
            );
            return $out;
        }

        $fieldsByLabel = $ctx->fieldsByLabel($containerId);
        foreach ($required as $candidates) {
            $label = null;
            foreach ($candidates as $cand) {
                if (isset($fieldsByLabel[mb_strtolower($cand)])) {
                    $label = $cand;
                    break;
                }
            }
            if ($label === null) {
                continue; // field not present in schema — cannot evaluate
            }
            if ($ctx->pluginIsEmpty($ticket, $containerId, $label)) {
                $out[] = $this->deviation(
                    "El campo obligatorio «{$label}» está vacío.",
                    $label, 'Con dato o NO PROPORCIONADO', '(vacío)',
                );
            }
        }

        return $out;
    }
}
