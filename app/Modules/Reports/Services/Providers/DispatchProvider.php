<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Providers;

use App\Modules\MailDispatch\Services\MailDispatchMetrics;
use App\Modules\Reports\Services\ReportPeriod;

/**
 * Mesa de Ayuda por Correo section: reuses MailDispatchMetrics::dashboard()
 * as-is (volume, dispositions, SLA, per-agent) for the reporting month —
 * no new query, MailDispatch already computes exactly what direction needs.
 */
class DispatchProvider implements ReportSectionProvider
{
    public function __construct(private MailDispatchMetrics $metrics) {}

    public function key(): string
    {
        return 'dispatch';
    }

    public function label(): string
    {
        return 'Mesa de Ayuda por Correo';
    }

    public function isAvailable(): bool
    {
        // MailDispatch has no "configured" flag of its own; an empty range
        // is a legitimate, available answer (zero mail that month).
        return true;
    }

    public function collect(ReportPeriod $period): array
    {
        $data = $this->metrics->dashboard($period->startDate, $period->endDate, null);
        return ['available' => true] + $data;
    }
}
