<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

use App\Modules\HelpdeskSupervisor\Services\GlpiAuditQueryService;

/**
 * Abandoned tickets (Manual Parte 4.1 - Propiedad del ticket; KPI 4). An OPEN
 * ticket whose last activity by the owning agent is older than N business days
 * (configurable, default 5) is abandoned. Only the agent's own activity counts;
 * business days exclude Saturday and Sunday.
 */
class AbandonedTicketsRule extends AbstractRule
{
    public function key(): string { return 'abandoned_tickets'; }
    public function name(): string { return 'Ticket abandonado'; }
    public function manualReference(): string { return 'Parte 4.1 - Propiedad del ticket'; }
    public function kpiMapping(): ?string { return 'KPI-4'; }
    public function severity(): string { return 'critical'; }

    public function evaluate(array $ticket, AuditContext $ctx): array
    {
        // Only open tickets (not resolved/closed) are the agent's live burden.
        if (! in_array((int) $ticket['status'], GlpiAuditQueryService::OPEN_STATUSES, true)) {
            return [];
        }

        // Baseline = most recent of: agent's last update, or the ticket opening
        // (the agent created it, so opening counts as agent activity).
        $lastActivity = (string) ($ticket['activity']['last_agent_activity'] ?? '');
        $baseline = $lastActivity !== '' ? $lastActivity : (string) $ticket['date'];
        $baselineTs = strtotime($baseline);
        if ($baselineTs === false) {
            return [];
        }

        $businessDays = $this->businessDaysBetween($baselineTs, time());
        $threshold    = $ctx->businessDaysAbandonment;

        if ($businessDays > $threshold) {
            return [$this->deviation(
                "El ticket está abierto sin actividad del agente desde hace {$businessDays} días hábiles (umbral: {$threshold}).",
                'Última actividad del agente',
                "<= {$threshold} días hábiles",
                date('d/m/Y', $baselineTs),
            )];
        }

        return [];
    }

    /** Weekdays (Mon-Fri) between two timestamps, capped for safety. */
    private function businessDaysBetween(int $fromTs, int $toTs): int
    {
        if ($toTs <= $fromTs) {
            return 0;
        }
        $start = strtotime(date('Y-m-d', $fromTs));
        $end   = strtotime(date('Y-m-d', $toTs));
        $days  = 0;
        $guard = 0;
        for ($d = $start; $d < $end && $guard < 1000; $d += 86400, $guard++) {
            if ((int) date('N', $d) < 6) {
                $days++;
            }
        }
        return $days;
    }
}
