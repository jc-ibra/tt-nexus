<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

/**
 * A natural calendar month (day 1 to the last day), the unit every provider
 * collects data for. Immutable value object so providers cannot accidentally
 * widen/narrow the range they were asked to report on.
 */
final class ReportPeriod
{
    public readonly string $start; // Y-m-d 00:00:00
    public readonly string $end;   // Y-m-d 23:59:59
    public readonly string $startDate; // Y-m-d
    public readonly string $endDate;   // Y-m-d
    public readonly string $label; // "Agosto 2026"

    public function __construct(
        public readonly int $year,
        public readonly int $month,
    ) {
        $this->startDate = sprintf('%04d-%02d-01', $year, $month);
        $this->endDate   = date('Y-m-t', strtotime($this->startDate));
        $this->start     = $this->startDate . ' 00:00:00';
        $this->end       = $this->endDate . ' 23:59:59';
        $this->label     = ucfirst(self::monthName($month)) . ' ' . $year;
    }

    /** Spanish month name, since PHP 8.1+ dropped strftime(). */
    private static function monthName(int $month): string
    {
        static $names = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        return $names[$month] ?? (string) $month;
    }

    public static function fromYearMonth(int $year, int $month): self
    {
        return new self($year, $month);
    }

    /** The natural month immediately before this one. */
    public function previous(): self
    {
        $ts = strtotime('-1 month', strtotime($this->startDate));
        return new self((int) date('Y', $ts), (int) date('n', $ts));
    }

    /** The same calendar month, one year earlier. */
    public function previousYear(): self
    {
        return new self($this->year - 1, $this->month);
    }

    /** The N calendar months up to and including this one, oldest first. */
    public function trailing(int $months): array
    {
        $out = [];
        $cur = $this;
        for ($i = 0; $i < $months; $i++) {
            array_unshift($out, $cur);
            $cur = $cur->previous();
        }
        return $out;
    }
}
