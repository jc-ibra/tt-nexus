<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Providers;

use App\Modules\HelpdeskSupervisor\Services\HelpdeskSupervisorBridge;
use App\Modules\Reports\Services\ReportPeriod;

/**
 * Calidad Documental section: the audit compliance summary for the month,
 * via the read-only HelpdeskSupervisorBridge — never the supervisor tables
 * directly.
 */
class QualityProvider implements ReportSectionProvider
{
    public function __construct(private HelpdeskSupervisorBridge $bridge) {}

    public function key(): string
    {
        return 'quality';
    }

    public function label(): string
    {
        return 'Calidad Documental';
    }

    public function isAvailable(): bool
    {
        // Always attempted: periodQualitySummary itself reports
        // ['available' => false] when no audit run has completed yet, which
        // is a legitimate "not generated" state, not a failure.
        return true;
    }

    public function collect(ReportPeriod $period): array
    {
        return $this->bridge->periodQualitySummary($period->year, $period->month);
    }
}
