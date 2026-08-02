<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Reclassification (Manual Parte 3.3 - Categoría; KPI 2). If the Category or
 * Type of a ticket changed after creation, the agent did not classify it
 * correctly when opening it.
 *
 * Detected from glpi_logs: a change entry whose search option is the category or
 * type and whose old value was non-empty (i.e. it had a previous value).
 *
 * GLPI standard ticket search options: 7 = itilcategories_id, 14 = type. If the
 * target instance customizes these, adjust the constants.
 */
class ReclassificationRule extends AbstractRule
{
    private const SO_CATEGORY = 7;
    private const SO_TYPE     = 14;

    public function key(): string { return 'reclassification'; }
    public function name(): string { return 'Reclasificación posterior'; }
    public function manualReference(): string { return 'Parte 3.3 - Categoría'; }
    public function kpiMapping(): ?string { return 'KPI-2'; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $out = [];
        foreach (($ticket['logs'] ?? []) as $log) {
            $so = (int) ($log['id_search_option'] ?? 0);
            if ($so !== self::SO_CATEGORY && $so !== self::SO_TYPE) {
                continue;
            }
            if (trim((string) ($log['old_value'] ?? '')) === '') {
                continue; // initial assignment, not a reclassification
            }

            $field = $so === self::SO_CATEGORY ? 'Categoría' : 'Tipo';
            $out[] = $this->deviation(
                "El campo {$field} se modificó después de crear el ticket: indica una clasificación incorrecta al abrir.",
                $field,
                (string) $log['old_value'],
                (string) $log['new_value'],
            );
        }
        return $out;
    }
}
