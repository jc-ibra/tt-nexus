<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\MailDispatch\Models\BusinessExceptionModel;

/**
 * The service calendar behind every SLA clock in Despacho.
 *
 * Wall-clock minutes punish the mesa for hours nobody was working: a mail that
 * lands Friday at 18:55 has burned a 120-minute SLA before Monday even starts.
 * This service answers the same questions in *business* minutes, using the
 * weekly schedule (one window per weekday) plus the dated exceptions
 * (holidays, one-off closures or reduced hours).
 *
 * Two operations cover every call site:
 *
 *  - `elapsedMinutes($from)` — how much of the budget a thread has consumed.
 *    Used wherever a row is rendered.
 *  - `cutoff($minutes)` — the wall-clock instant such that `received_at <
 *    cutoff` means exactly "consumed more than $minutes business minutes".
 *    Lets the breach query stay a single indexed SQL comparison instead of
 *    walking the calendar per row.
 *
 * With the calendar off (the default) both fall back to plain wall-clock math,
 * so behaviour is byte-for-byte what it was before the feature existed.
 */
class BusinessCalendar
{
    /** Defensive bound on the day-by-day walk (~10 years). */
    private const MAX_DAYS = 3660;

    /** ISO-8601 weekday numbering, which is what date('N') returns. */
    public const DAY_LABELS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /** @var array<int,array{closed:bool,open:string,close:string}>|null */
    private ?array $schedule = null;

    /** @var array<string,array<string,mixed>>|null */
    private ?array $exceptions = null;

    /** @var array<string,array<int,array{0:int,1:int}>> windows memoized per date */
    private array $windowCache = [];

    public function __construct(
        private MailDispatchSettings $settings,
        private BusinessExceptionModel $exceptionsModel
    ) {}

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    public function isEnabled(): bool
    {
        return $this->settings->businessHoursEnabled();
    }

    /**
     * Business minutes between two datetimes. Returns 0 when either side is
     * missing or the range is inverted.
     */
    public function minutesBetween(?string $from, ?string $to): int
    {
        $fromTs = $from ? strtotime($from) : false;
        $toTs   = $to ? strtotime($to) : false;
        if ($fromTs === false || $toTs === false || $toTs <= $fromTs) {
            return 0;
        }

        if (! $this->isEnabled()) {
            return (int) floor(($toTs - $fromTs) / 60);
        }

        $seconds = 0;
        $day     = strtotime(date('Y-m-d', $fromTs));
        $guard   = 0;

        while ($day !== false && $day <= $toTs && $guard++ < self::MAX_DAYS) {
            foreach ($this->windowsFor(date('Y-m-d', $day)) as [$ws, $we]) {
                $start = max($ws, $fromTs);
                $end   = min($we, $toTs);
                if ($end > $start) {
                    $seconds += $end - $start;
                }
            }
            $day = strtotime('+1 day', $day);
        }

        return (int) floor($seconds / 60);
    }

    /** Business minutes consumed since $from (now by default). */
    public function elapsedMinutes(?string $from, ?int $nowTs = null): int
    {
        $nowTs ??= time();
        return $this->minutesBetween($from, date('Y-m-d H:i:s', $nowTs));
    }

    /**
     * The wall-clock instant $minutes business minutes before now, as
     * 'Y-m-d H:i:s'. A conversation whose `received_at` is strictly earlier has
     * consumed more than $minutes business minutes — which is precisely the
     * breach condition, expressible in SQL.
     *
     * When the schedule can never accumulate the budget (every day closed) it
     * returns the epoch, so nothing is ever flagged.
     */
    public function cutoff(int $minutes, ?int $nowTs = null): string
    {
        $nowTs ??= time();

        if ($minutes <= 0) {
            return date('Y-m-d H:i:s', $nowTs);
        }
        if (! $this->isEnabled()) {
            return date('Y-m-d H:i:s', $nowTs - $minutes * 60);
        }

        $remaining   = $minutes * 60;
        $day         = strtotime(date('Y-m-d', $nowTs));
        $guard       = 0;
        $landing     = null;
        $windowStart = null;

        while ($day !== false && $guard++ < self::MAX_DAYS) {
            // Latest window first: we are consuming the budget backwards.
            foreach (array_reverse($this->windowsFor(date('Y-m-d', $day))) as [$ws, $we]) {
                $we = min($we, $nowTs); // the future does not count
                if ($we <= $ws) {
                    continue;
                }
                $span = $we - $ws;
                if ($span < $remaining) {
                    $remaining -= $span;
                    continue;
                }
                $landing     = $we - $remaining;
                $windowStart = $ws;
                break 2;
            }
            $day = strtotime('-1 day', $day);
        }

        if ($landing === null) {
            return '1970-01-01 00:00:00';
        }

        // Landing exactly on an opening time means the whole closed gap before it
        // consumed the same budget, so the real frontier is the previous close.
        if ($landing === $windowStart) {
            $prev = $this->previousWindowEnd($landing);
            return $prev === null ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', $prev);
        }

        return date('Y-m-d H:i:s', $landing);
    }

    /** Whether the mesa is inside a service window right now. */
    public function isOpenAt(?int $ts = null): bool
    {
        $ts ??= time();
        if (! $this->isEnabled()) {
            return true;
        }
        foreach ($this->windowsFor(date('Y-m-d', $ts)) as [$ws, $we]) {
            if ($ts >= $ws && $ts < $we) {
                return true;
            }
        }
        return false;
    }

    /**
     * The weekly schedule, normalized and keyed 1..7, for the admin form and the
     * API. Always returns the seven days.
     *
     * @return array<int,array{closed:bool,open:string,close:string}>
     */
    public function weeklySchedule(): array
    {
        if ($this->schedule === null) {
            $this->schedule = $this->normalizeSchedule($this->settings->businessHoursSchedule());
        }
        return $this->schedule;
    }

    /**
     * How many minutes a "day" is worth when formatting an elapsed span. With
     * the calendar on, a day of silence means a *service* day (the average open
     * window), not 24 hours: saying "2 d" about 2880 business minutes would
     * otherwise mean a full working week.
     */
    public function minutesPerDay(): int
    {
        if (! $this->isEnabled()) {
            return 1440;
        }

        $total = 0;
        $days  = 0;
        foreach ($this->weeklySchedule() as $row) {
            if ($row['closed']) {
                continue;
            }
            $open  = strtotime('1970-01-02 ' . $row['open'] . ':00');
            $close = strtotime('1970-01-02 ' . $row['close'] . ':00');
            if ($open === false || $close === false || $close <= $open) {
                continue;
            }
            $total += (int) (($close - $open) / 60);
            $days++;
        }

        return $days > 0 ? max(1, (int) round($total / $days)) : 1440;
    }

    /**
     * One-line summary of the schedule for headers and help text, e.g.
     * "Lunes a viernes 09:00-19:00 · Sábado 09:00-15:00".
     */
    public function summary(): string
    {
        $groups = [];
        foreach ($this->weeklySchedule() as $dow => $row) {
            if ($row['closed']) {
                continue;
            }
            $key = $row['open'] . '-' . $row['close'];
            $groups[$key][] = $dow;
        }

        if ($groups === []) {
            return 'Sin días de servicio configurados';
        }

        $parts = [];
        foreach ($groups as $window => $days) {
            sort($days);
            // Consecutive runs collapse into "Lunes a viernes".
            $isRun  = count($days) > 2 && ($days[count($days) - 1] - $days[0]) === count($days) - 1;
            $label  = $isRun
                ? self::DAY_LABELS[$days[0]] . ' a ' . mb_strtolower(self::DAY_LABELS[$days[count($days) - 1]])
                : implode(', ', array_map(static fn(int $d): string => self::DAY_LABELS[$d], $days));
            $parts[] = $label . ' ' . str_replace('-', ' a ', $window);
        }

        return implode(' · ', $parts);
    }

    /** Exceptions as stored, keyed by 'Y-m-d'. */
    public function exceptionMap(): array
    {
        if ($this->exceptions === null) {
            $this->exceptions = $this->exceptionsModel->map();
        }
        return $this->exceptions;
    }

    /** Drops the memoized schedule/exceptions after an admin save. */
    public function refresh(): void
    {
        $this->schedule    = null;
        $this->exceptions  = null;
        $this->windowCache = [];
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Service windows of a single date as [startTs, endTs] pairs — empty when
     * closed. A dated exception wins over the weekday row.
     *
     * @return array<int,array{0:int,1:int}>
     */
    private function windowsFor(string $date): array
    {
        if (isset($this->windowCache[$date])) {
            return $this->windowCache[$date];
        }

        $dayTs = strtotime($date);
        if ($dayTs === false) {
            return $this->windowCache[$date] = [];
        }

        $weekly = $this->weeklySchedule()[(int) date('N', $dayTs)] ?? null;
        $open   = $weekly && ! $weekly['closed'] ? $weekly['open'] : '';
        $close  = $weekly && ! $weekly['closed'] ? $weekly['close'] : '';

        $ex = $this->exceptionMap()[$date] ?? null;
        if ($ex !== null) {
            if ((int) ($ex['is_closed'] ?? 1) === 1) {
                return $this->windowCache[$date] = [];
            }
            // A reduced-hours exception may leave a side blank; the weekday value
            // then stands in for it.
            $exOpen  = substr((string) ($ex['open_time'] ?? ''), 0, 5);
            $exClose = substr((string) ($ex['close_time'] ?? ''), 0, 5);
            $open    = $exOpen !== '' ? $exOpen : $open;
            $close   = $exClose !== '' ? $exClose : $close;
        }

        if ($open === '' || $close === '') {
            return $this->windowCache[$date] = [];
        }

        $ws = strtotime($date . ' ' . $open . ':00');
        $we = strtotime($date . ' ' . $close . ':00');
        if ($ws === false || $we === false || $we <= $ws) {
            return $this->windowCache[$date] = [];
        }

        return $this->windowCache[$date] = [[$ws, $we]];
    }

    /** End of the last service window that closes strictly before $ts. */
    private function previousWindowEnd(int $ts): ?int
    {
        $day   = strtotime(date('Y-m-d', $ts));
        $guard = 0;

        while ($day !== false && $guard++ < self::MAX_DAYS) {
            foreach (array_reverse($this->windowsFor(date('Y-m-d', $day))) as [, $we]) {
                if ($we < $ts) {
                    return $we;
                }
            }
            $day = strtotime('-1 day', $day);
        }

        return null;
    }

    /**
     * Fills in the seven days and clamps the times, so a hand-edited or partial
     * JSON never breaks the clock.
     *
     * @return array<int,array{closed:bool,open:string,close:string}>
     */
    private function normalizeSchedule(array $raw): array
    {
        $out = [];
        foreach (array_keys(self::DAY_LABELS) as $dow) {
            $row    = $raw[$dow] ?? $raw[(string) $dow] ?? [];
            $open   = self::normalizeTime((string) ($row['open'] ?? ''), '09:00');
            $close  = self::normalizeTime((string) ($row['close'] ?? ''), '18:00');
            $closed = ! empty($row['closed']) || $close <= $open;

            $out[$dow] = ['closed' => $closed, 'open' => $open, 'close' => $close];
        }
        return $out;
    }

    /** Clamps 'H:i' (or 'H:i:s') to a valid time string, or returns $default. */
    public static function normalizeTime(string $value, string $default = ''): string
    {
        $value = trim($value);
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return $default;
        }
        $h = min(23, max(0, (int) $m[1]));
        $i = min(59, max(0, (int) $m[2]));
        return sprintf('%02d:%02d', $h, $i);
    }
}
