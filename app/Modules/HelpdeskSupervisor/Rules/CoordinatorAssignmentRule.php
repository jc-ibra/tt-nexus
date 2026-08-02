<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Coordinator assignment (Manual Parte 3.5). For clientes externos with dynamic
 * by-zone assignment, the ticket's assigned user must be the operational
 * coordinator that corresponds to the state captured in the Clientes Externos
 * tab. Auto-assigned categories (Edificios, Data Center, and the fixed-owner
 * ones) are out of scope for this rule.
 */
class CoordinatorAssignmentRule extends AbstractRule
{
    public function key(): string { return 'coordinator_assignment'; }
    public function name(): string { return 'Asignación de coordinador incorrecta'; }
    public function manualReference(): string { return 'Parte 3.5 - Asignación del ticket'; }
    public function kpiMapping(): ?string { return null; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $c = $this->classifier->classify((string) $ticket['category_name']);
        if (! $c['isCE'] || $c['assignment'] !== 'coordinator') {
            return [];
        }

        $containerId = $ctx->tabContainerId('clientes_externos');
        if ($containerId === 0) {
            return [];
        }

        $state = trim($ctx->pluginDisplayByLabel($ticket, $containerId, 'Estado'));
        if ($state === '') {
            return []; // no state captured — cannot determine expected coordinator
        }

        $coordinator = $ctx->coordinatorByState[mb_strtolower($state)] ?? null;
        if ($coordinator === null) {
            return []; // unknown state — not this rule's job to flag
        }

        $expectedId   = (int) ($coordinator['coordinator_glpi_user_id'] ?? 0);
        $expectedName = (string) ($coordinator['coordinator_name'] ?? '');
        if ($expectedId === 0) {
            return []; // coordinator id unresolved — cannot compare reliably
        }

        $assigned = array_map('intval', (array) ($ticket['assigned_user_ids'] ?? []));
        if (! in_array($expectedId, $assigned, true)) {
            return [$this->deviation(
                "El ticket del estado «{$state}» debería estar asignado al coordinador {$expectedName}.",
                'Asignado a', $expectedName, $assigned === [] ? '(sin asignar)' : ('IDs: ' . implode(',', $assigned)),
            )];
        }

        return [];
    }
}
