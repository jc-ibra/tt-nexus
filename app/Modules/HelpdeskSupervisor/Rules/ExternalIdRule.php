<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * External ID (Manual Parte 3.3 - ID Externo). Always required: the client's
 * ticket number, or NO PROPORCIONADO / NO APLICA. Read from the native GLPI
 * field glpi_tickets.externalid (not a plugin container).
 *  - Empty = deviation.
 *  - Clientes Externos must not be NO APLICA (use NO PROPORCIONADO if missing).
 *  - Pure internal categories must not be NO PROPORCIONADO (use NO APLICA).
 */
class ExternalIdRule extends AbstractRule
{
    public function key(): string { return 'external_id'; }
    public function name(): string { return 'ID Externo incorrecto'; }
    public function manualReference(): string { return 'Parte 3.3 - ID Externo'; }
    public function kpiMapping(): ?string { return null; }
    public function severity(): string { return 'info'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $c = $this->classifier->classify((string) $ticket['category_name']);
        if ($c['outOfScope']) {
            return [];
        }

        $value = trim((string) ($ticket['external_id'] ?? ''));
        if ($value === '') {
            return [$this->deviation(
                'El ID Externo está vacío; siempre debe llevar el número del cliente, NO PROPORCIONADO o NO APLICA.',
                'ID Externo', 'Número del cliente / NO PROPORCIONADO / NO APLICA', '(vacío)',
            )];
        }

        $u = mb_strtoupper($value);
        $isInternal = $c['branch'] === 'ai' || ($c['branch'] === 'ad' && $c['tab'] === 'areas_internas');

        if ($c['isCE'] && $u === 'NO APLICA') {
            return [$this->deviation(
                'En clientes externos el ID Externo no debe ser NO APLICA; usa NO PROPORCIONADO si no lo dieron.',
                'ID Externo', 'Número del cliente o NO PROPORCIONADO', $value,
            )];
        }
        if ($isInternal && $u === 'NO PROPORCIONADO') {
            return [$this->deviation(
                'En categorías internas el ID Externo debe ser NO APLICA, no NO PROPORCIONADO.',
                'ID Externo', 'NO APLICA', $value,
            )];
        }

        return [];
    }
}
