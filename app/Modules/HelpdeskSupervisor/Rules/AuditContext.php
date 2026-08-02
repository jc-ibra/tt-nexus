<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Shared, read-only lookups handed to every rule during a run so they never hit
 * the database themselves. Assembled once by AuditRunnerService.
 */
class AuditContext
{
    /**
     * @param array<int,string>                  $categories        itilcategories_id => completename
     * @param array<string,array<string,mixed>>  $coordinatorByState normalized state => coordinator row
     * @param array<int,array<string,mixed>>     $containers        containerId => introspector container meta {id,name,label,dataTable,fields}
     * @param array<string,int>                  $tabContainers     tab key => container id (clientes_externos, areas_internas, control_activos, control_envios, ids, external_id)
     * @param int                                $openingDateToleranceSeconds
     * @param int                                $businessDaysAbandonment
     */
    /**
     * @param array<int,string>                  $categories        itilcategories_id => completename
     * @param array<string,array<string,mixed>>  $coordinatorByState normalized state => coordinator row (with resolved glpi id)
     * @param array<int,array<string,mixed>>     $containers        containerId => introspector container meta
     * @param array<string,int>                  $tabContainers     tab key => container id
     * @param array<int,array<string,array<int,string>>> $dropdownValues containerId => labelLower => (id => name)
     */
    public function __construct(
        public readonly array $categories = [],
        public readonly array $coordinatorByState = [],
        public readonly array $containers = [],
        public readonly array $tabContainers = [],
        public readonly int $openingDateToleranceSeconds = 60,
        public readonly int $businessDaysAbandonment = 5,
        public readonly array $dropdownValues = [],
    ) {}

    /** Container id configured for a logical tab, or 0 if unset. */
    public function tabContainerId(string $tabKey): int
    {
        return (int) ($this->tabContainers[$tabKey] ?? 0);
    }

    /**
     * Field metadata (name,label,type,column,dropdownTable,mandatory) for a
     * container, keyed by the field's lowercased label for label-based lookup.
     *
     * @return array<string,array<string,mixed>>
     */
    public function fieldsByLabel(int $containerId): array
    {
        $out = [];
        foreach (($this->containers[$containerId]['fields'] ?? []) as $f) {
            $out[mb_strtolower(trim((string) $f['label']))] = $f;
        }
        return $out;
    }

    /**
     * Raw plugin value for a ticket in a container, by field label. Returns null
     * when the container/field/row is not present. Dropdown fields return the FK
     * id (0 = empty); scalar fields return the stored text.
     *
     * @param array<string,mixed> $ticket normalized ticket
     */
    public function pluginValueByLabel(array $ticket, int $containerId, string $label): mixed
    {
        $row = $ticket['plugin'][$containerId] ?? null;
        if ($row === null) {
            return null;
        }
        $field = $this->fieldsByLabel($containerId)[mb_strtolower(trim($label))] ?? null;
        if ($field === null) {
            return null;
        }
        return $row[$field['column']] ?? null;
    }

    /** True when a plugin field (by label) is effectively empty for the ticket. */
    public function pluginIsEmpty(array $ticket, int $containerId, string $label): bool
    {
        $field = $this->fieldsByLabel($containerId)[mb_strtolower(trim($label))] ?? null;
        if ($field === null) {
            // Field not present in schema: treat as "cannot evaluate" -> not empty.
            return false;
        }
        $value = $this->pluginValueByLabel($ticket, $containerId, $label);

        if (($field['type'] ?? '') === 'dropdown') {
            return $value === null || (int) $value === 0;
        }
        return $value === null || trim((string) $value) === '';
    }

    /**
     * Human-readable value for a plugin field by label. For dropdown fields it
     * resolves the FK id to its catalog name; for scalar fields it returns the
     * stored text. Returns '' when unresolved/empty.
     *
     * @param array<string,mixed> $ticket
     */
    public function pluginDisplayByLabel(array $ticket, int $containerId, string $label): string
    {
        $field = $this->fieldsByLabel($containerId)[mb_strtolower(trim($label))] ?? null;
        if ($field === null) {
            return '';
        }
        $value = $this->pluginValueByLabel($ticket, $containerId, $label);
        if ($value === null) {
            return '';
        }
        if (($field['type'] ?? '') === 'dropdown') {
            $id = (int) $value;
            if ($id === 0) {
                return '';
            }
            $labelLower = mb_strtolower(trim($label));
            return (string) ($this->dropdownValues[$containerId][$labelLower][$id] ?? '');
        }
        return trim((string) $value);
    }

    /** True when the ticket has at least one non-empty field in the container. */
    public function pluginHasAnyData(array $ticket, int $containerId): bool
    {
        if (! isset($ticket['plugin'][$containerId])) {
            return false;
        }
        foreach ($this->fieldsByLabel($containerId) as $label => $_field) {
            if (! $this->pluginIsEmpty($ticket, $containerId, (string) $label)) {
                return true;
            }
        }
        return false;
    }

    public function categoryName(int $categoryId): string
    {
        return $this->categories[$categoryId] ?? '';
    }
}
