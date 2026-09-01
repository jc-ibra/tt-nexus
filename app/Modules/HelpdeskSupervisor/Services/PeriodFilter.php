<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * Resolves audit/overview date ranges from month/year shortcuts or custom dates.
 */
class PeriodFilter
{
    /** @return array<int,string> */
    public static function monthLabels(): array
    {
        return [
            1  => 'Enero',
            2  => 'Febrero',
            3  => 'Marzo',
            4  => 'Abril',
            5  => 'Mayo',
            6  => 'Junio',
            7  => 'Julio',
            8  => 'Agosto',
            9  => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }

    /**
     * month+year shortcut wins when both are valid; otherwise period_start/end.
     *
     * @return array{0:string,1:string} [start Y-m-d, end Y-m-d]
     */
    public static function resolveFromRequest(IncomingRequest $request): array
    {
        $month = (int) $request->getGet('month');
        $year  = (int) $request->getGet('year');

        if ($month >= 1 && $month <= 12 && $year >= 2020 && $year <= 2100) {
            $start = sprintf('%04d-%02d-01', $year, $month);

            return [$start, date('Y-m-t', strtotime($start))];
        }

        return self::normalizeRange(
            (string) $request->getGet('period_start'),
            (string) $request->getGet('period_end'),
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizeRange(string $start, string $end): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = date('Y-m-t', strtotime($start));
        }

        return [$start, $end];
    }

    /** When the range is exactly one calendar month, returns its month and year. */
    public static function calendarMonth(string $start, string $end): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return null;
        }

        $ts = strtotime($start);
        if ($ts === false) {
            return null;
        }

        $monthStart = date('Y-m-01', $ts);
        $monthEnd   = date('Y-m-t', $ts);

        if ($start !== $monthStart || $end !== $monthEnd) {
            return null;
        }

        return [
            'month' => (int) date('n', $ts),
            'year'  => (int) date('Y', $ts),
        ];
    }
}
