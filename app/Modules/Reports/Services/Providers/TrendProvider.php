<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Providers;

use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\Reports\Services\ReportPeriod;
use App\Modules\Reports\Services\ReportsSettingsService;

/**
 * Trend and month-over-month / year-over-year comparatives, plus a live
 * backlog aging breakdown. Reuses GlpiTicketsProvider for each trailing
 * month instead of reading past snapshots, so the trend line is correct
 * even for months that were never generated as a report (a live re-query is
 * cheap: this only runs once, in the monthly batch job).
 */
class TrendProvider implements ReportSectionProvider
{
    /** Backlog ticket considered critical past this many days open. */
    private const CRITICAL_DAYS = 15;

    public function __construct(
        private GlpiTicketsProvider $glpiTickets,
        private GlpiDbConnection $glpi,
        private ReportsSettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'trend';
    }

    public function label(): string
    {
        return 'Tendencia y comparativas';
    }

    public function isAvailable(): bool
    {
        return $this->glpiTickets->isAvailable();
    }

    public function collect(ReportPeriod $period): array
    {
        $months = $period->trailing($this->settings->trendMonths());

        $series = [];
        $byKey  = [];
        foreach ($months as $m) {
            $data = $this->glpiTickets->collect($m);
            $row  = [
                'period'      => $m->label,
                'year'        => $m->year,
                'month'       => $m->month,
                'total'       => $data['total'],
                'cerrados'    => $data['cerrados'],
                'en_curso'    => $data['en_curso'],
                'backlog_net' => $data['total'] - $data['cerrados'],
                'sla_pct'     => $data['sla_pct'],
                'tasa_cierre' => $data['tasa_cierre'],
            ];
            $series[] = $row;
            $byKey[$m->year . '-' . $m->month] = $row;
        }

        $current       = $byKey[$period->year . '-' . $period->month] ?? end($series) ?: null;
        $previousMonth = $period->previous();
        $previousYear  = $period->previousYear();

        return [
            'available'      => true,
            'series'         => $series,
            'vs_previous_month' => $this->delta($current, $byKey[$previousMonth->year . '-' . $previousMonth->month] ?? null),
            'vs_previous_year'  => $this->delta($current, $byKey[$previousYear->year . '-' . $previousYear->month] ?? null),
            'backlog_aging'  => $this->backlogAging(),
        ];
    }

    /** @return array<string,array{current:mixed,previous:mixed,delta_abs:?float,delta_pct:?float}>|null */
    private function delta(?array $current, ?array $previous): ?array
    {
        if ($current === null || $previous === null) {
            return null;
        }
        $out = [];
        foreach (['total', 'cerrados', 'tasa_cierre', 'sla_pct'] as $metric) {
            $cur  = (float) $current[$metric];
            $prev = (float) $previous[$metric];
            $out[$metric] = [
                'current'   => $current[$metric],
                'previous'  => $previous[$metric],
                'delta_abs' => round($cur - $prev, 2),
                'delta_pct' => $prev != 0.0 ? round(($cur - $prev) / $prev * 100, 2) : null,
            ];
        }
        return $out;
    }

    /**
     * Live snapshot of the OPEN backlog (regardless of when it was opened),
     * bucketed by age. This is deliberately not period-scoped: a report for
     * August still wants to know "how old is what's open today".
     */
    private function backlogAging(): array
    {
        if (! $this->glpi->isConfigured()) {
            return ['available' => false];
        }

        $rows = $this->glpi->connection()->table('glpi_tickets')
            ->select('date')
            ->where('is_deleted', 0)
            ->whereIn('status', [1, 2, 3, 4]) // open statuses
            ->get()->getResultArray();

        $buckets = ['0-3' => 0, '4-7' => 0, '8-15' => 0, '16-30' => 0, '31+' => 0];
        $critical = 0;
        $now = time();

        foreach ($rows as $r) {
            $days = (int) floor(($now - strtotime((string) $r['date'])) / 86400);
            $buckets[match (true) {
                $days <= 3  => '0-3',
                $days <= 7  => '4-7',
                $days <= 15 => '8-15',
                $days <= 30 => '16-30',
                default     => '31+',
            }]++;
            if ($days > self::CRITICAL_DAYS) {
                $critical++;
            }
        }

        return [
            'available'      => true,
            'total_open'     => count($rows),
            'buckets'        => $buckets,
            'critical_days'  => self::CRITICAL_DAYS,
            'critical_count' => $critical,
        ];
    }
}
