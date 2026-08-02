<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Title nomenclature (Manual Parte 3.3 / Anexo A).
 *  - The whole title must be UPPERCASE.
 *  - Clientes Externos: "CLIENTE - SUCURSAL - DESCRIPCIÓN" (>= 3 segments). For
 *    Edificios/Data Center the SUCURSAL segment is the fixed convention.
 *  - Internal categories: "NOMBRE CATEGORÍA - DESCRIPCIÓN" (starts with the leaf).
 */
class TitleFormatRule extends AbstractRule
{
    public function key(): string { return 'title_format'; }
    public function name(): string { return 'Título mal formado'; }
    public function manualReference(): string { return 'Parte 3.3 - Título'; }
    public function kpiMapping(): ?string { return null; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $title = trim((string) $ticket['name']);
        if ($title === '') {
            return []; // emptiness handled by field_completeness
        }

        $c = $this->classifier->classify((string) $ticket['category_name']);
        if ($c['outOfScope']) {
            return [];
        }

        $out = [];

        // 1) Fully uppercase.
        if ($title !== $this->upper($title)) {
            $out[] = $this->deviation(
                'El título debe estar completamente en MAYÚSCULAS.',
                'Título', $this->upper($title), $title,
            );
        }

        $segments = array_map('trim', explode(' - ', $title));

        if ($c['isCE']) {
            // CE: CLIENTE - SUCURSAL - DESCRIPCIÓN
            if (count($segments) < 3) {
                $out[] = $this->deviation(
                    'Los tickets de clientes externos deben seguir el patrón CLIENTE - SUCURSAL - DESCRIPCIÓN.',
                    'Título', 'CLIENTE - SUCURSAL - DESCRIPCIÓN', $title,
                );
            } else {
                // Fixed SUCURSAL convention for Edificios / Data Center.
                $sucursal = $this->upper($segments[1]);
                if ($c['isEdificios'] && $sucursal !== 'EDIFICIOS') {
                    $out[] = $this->deviation(
                        'En categorías Edificios el segmento de sucursal debe ser EDIFICIOS.',
                        'Título (sucursal)', 'EDIFICIOS', $segments[1],
                    );
                }
                if ($c['isDataCenter'] && $sucursal !== 'DATA CENTER') {
                    $out[] = $this->deviation(
                        'En categorías Data Center el segmento de sucursal debe ser DATA CENTER.',
                        'Título (sucursal)', 'DATA CENTER', $segments[1],
                    );
                }
                // Client segment should match the category client.
                $client = $this->upper((string) $c['client']);
                if ($client !== '' && ! $this->clientMatches($this->upper($segments[0]), $client)) {
                    $out[] = $this->deviation(
                        'El segmento CLIENTE del título no coincide con el cliente de la categoría.',
                        'Título (cliente)', $client, $segments[0],
                    );
                }
            }
        } elseif (in_array($c['branch'], ['ai', 'ad'], true) && $c['tab'] !== '') {
            // Internal: NOMBRE CATEGORÍA - DESCRIPCIÓN
            $expectedPrefix = $this->classifier->internalTitlePrefix($c);
            if ($expectedPrefix !== '') {
                $prefix = $this->upper($segments[0]);
                if (count($segments) < 2) {
                    $out[] = $this->deviation(
                        'Las categorías internas deben seguir el patrón NOMBRE CATEGORÍA - DESCRIPCIÓN.',
                        'Título', $expectedPrefix . ' - DESCRIPCIÓN', $title,
                    );
                } elseif ($prefix !== $expectedPrefix) {
                    $out[] = $this->deviation(
                        'El título de una categoría interna debe iniciar con el nombre de la categoría.',
                        'Título (categoría)', $expectedPrefix, $segments[0],
                    );
                }
            }
        }

        return $out;
    }

    /** Lenient client match: exact, or the title segment starts with the client's first word. */
    private function clientMatches(string $segment, string $client): bool
    {
        if ($segment === $client) {
            return true;
        }
        $firstWord = explode(' ', $client)[0] ?? $client;
        return $firstWord !== '' && str_starts_with($segment, $firstWord);
    }
}
