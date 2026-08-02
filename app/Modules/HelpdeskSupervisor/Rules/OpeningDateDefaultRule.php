<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Default opening date (Manual Parte 3.3 - Fecha de apertura). The opening date
 * must be captured manually (the request date or the email date), never left at
 * the record creation default. When the captured date (date) is within a few
 * seconds of the record creation (date_creation), the agent likely left it by
 * default.
 */
class OpeningDateDefaultRule extends AbstractRule
{
    public function key(): string { return 'opening_date_default'; }
    public function name(): string { return 'Fecha de apertura por default'; }
    public function manualReference(): string { return 'Parte 3.3 - Fecha de apertura'; }
    public function kpiMapping(): ?string { return null; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $date     = trim((string) $ticket['date']);
        $creation = trim((string) $ticket['date_creation']);
        if ($date === '' || $creation === '') {
            return [];
        }

        $tsDate     = strtotime($date);
        $tsCreation = strtotime($creation);
        if ($tsDate === false || $tsCreation === false) {
            return [];
        }

        if (abs($tsDate - $tsCreation) < $ctx->openingDateToleranceSeconds) {
            return [$this->deviation(
                'La fecha de apertura parece haberse dejado por default (coincide con la creación del registro).',
                'Fecha de apertura', 'Fecha de la solicitud o del correo', $date,
            )];
        }

        return [];
    }
}
