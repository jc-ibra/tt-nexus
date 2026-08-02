<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

use App\Modules\HelpdeskSupervisor\Services\GlpiAuditQueryService;

/**
 * Active follow-up until closure (Manual Parte 4.1; KPI 1). For tickets resolved
 * or closed in the period, the agent must have registered at least one update
 * (followup, task or solution) of their own between opening and resolution.
 */
class FollowUpActivityRule extends AbstractRule
{
    public function key(): string { return 'followup_activity'; }
    public function name(): string { return 'Sin seguimiento del agente'; }
    public function manualReference(): string { return 'Parte 4.1 - Propiedad del ticket'; }
    public function kpiMapping(): ?string { return 'KPI-1'; }
    public function severity(): string { return 'warning'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        $status = (int) $ticket['status'];
        if ($status !== GlpiAuditQueryService::STATUS_SOLVED && $status !== GlpiAuditQueryService::STATUS_CLOSED) {
            return [];
        }

        $agentUpdates = (int) ($ticket['activity']['agent_updates'] ?? 0);
        if ($agentUpdates === 0) {
            return [$this->deviation(
                'El ticket se resolvió/cerró sin ningún seguimiento, tarea o solución registrada por el agente.',
                'Seguimiento del agente', '>= 1 actualización', '0 actualizaciones',
            )];
        }

        return [];
    }
}
