<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Providers;

use App\Modules\AgentKpis\Services\AgentKpisBridge;
use App\Modules\Reports\Services\ReportPeriod;

/**
 * Desempeño de Agentes section: the monthly evaluation summary, via the
 * read-only AgentKpisBridge — never the evaluation tables directly.
 */
class AgentPerformanceProvider implements ReportSectionProvider
{
    public function __construct(private AgentKpisBridge $bridge) {}

    public function key(): string
    {
        return 'agents';
    }

    public function label(): string
    {
        return 'Desempeño de Agentes';
    }

    public function isAvailable(): bool
    {
        // Always attempted: periodEvaluationSummary itself reports
        // ['available' => false] when the month has not been evaluated yet.
        return true;
    }

    public function collect(ReportPeriod $period): array
    {
        return $this->bridge->periodEvaluationSummary($period->year, $period->month);
    }
}
