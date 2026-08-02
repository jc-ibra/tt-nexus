<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Correct tab (Manual Parte 3.7). Only the tab that matches the category should
 * carry data; the other data tabs must stay empty. Flags a data tab that does
 * not correspond to the category but was filled anyway.
 *
 * Considers only the mutually-exclusive DATA tabs; IDS and ID Externo are
 * cross-cutting and handled by their own rules.
 */
class CorrectTabRule extends AbstractRule
{
    private const DATA_TABS = ['clientes_externos', 'areas_internas', 'control_activos', 'control_envios'];

    private const LABELS = [
        'clientes_externos' => 'Clientes Externos',
        'areas_internas'    => 'Áreas Internas',
        'control_activos'   => 'Control de Activos',
        'control_envios'    => 'Control de Envíos',
    ];

    public function key(): string { return 'correct_tab'; }
    public function name(): string { return 'Tab incorrecta llenada'; }
    public function manualReference(): string { return 'Parte 3.7 - Campos personalizados'; }
    public function kpiMapping(): ?string { return null; }
    public function severity(): string { return 'info'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $c = $this->classifier->classify((string) $ticket['category_name']);
        if ($c['outOfScope']) {
            return [];
        }
        $correctTab = (string) $c['tab'];
        if (! in_array($correctTab, self::DATA_TABS, true)) {
            return [];
        }
        $correctContainer = $ctx->tabContainerId($correctTab);

        $out = [];
        foreach (self::DATA_TABS as $tab) {
            if ($tab === $correctTab) {
                continue;
            }
            $cid = $ctx->tabContainerId($tab);
            // Skip unmapped tabs and any tab that shares the correct tab's container.
            if ($cid === 0 || $cid === $correctContainer) {
                continue;
            }
            if ($ctx->pluginHasAnyData($ticket, $cid)) {
                $out[] = $this->deviation(
                    'Se capturaron datos en la tab «' . (self::LABELS[$tab] ?? $tab)
                        . '», que no corresponde a la categoría del ticket.',
                    'Tab ' . (self::LABELS[$tab] ?? $tab),
                    'Solo la tab de la categoría (' . (self::LABELS[$correctTab] ?? $correctTab) . ')',
                    'Tab ' . (self::LABELS[$tab] ?? $tab) . ' con datos',
                );
            }
        }

        return $out;
    }
}
