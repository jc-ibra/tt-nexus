<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

/**
 * Small, dependency-free value normalizers shared by the validator and the
 * importer so both interpret cells identically.
 */
final class ValueParser
{
    // 2-digit-year variants come BEFORE their 4-digit counterparts on purpose:
    // '13/07/26 18:00' must match d/m/y (2026), not be misread. Strict parsing
    // (rejecting trailing data) prevents a 4-digit string from matching a
    // 2-digit format.
    private const DATE_FORMATS = [
        'd/m/y H:i:s', 'd/m/y H:i', 'd/m/y H.i.s', 'd/m/y H.i', 'd/m/y',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y H.i.s', 'd/m/Y H.i', 'd/m/Y',
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
        'd-m-y H:i', 'd-m-y', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
    ];

    /** UPPER(TRIM(v)) for case-insensitive matching. */
    public static function norm(mixed $v): string
    {
        return mb_strtoupper(trim((string) ($v ?? '')), 'UTF-8');
    }

    /** Trimmed string, capped, or null when empty. */
    public static function str(mixed $v, int $maxLen = 255): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '' || in_array(mb_strtoupper($s), ['N/A', 'NAN', 'NONE'], true)) {
            return null;
        }
        return mb_substr($s, 0, $maxLen);
    }

    /** True when a cell is effectively empty. */
    public static function isEmpty(mixed $v): bool
    {
        return trim((string) ($v ?? '')) === '';
    }

    /**
     * Parses a date cell (Excel serial or any known string format) to
     * 'Y-m-d H:i:s', or null when unparseable/empty.
     */
    public static function date(mixed $v): ?string
    {
        if (self::isEmpty($v)) {
            return null;
        }

        // Excel numeric serial date.
        if (is_numeric($v)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v);
                return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                // fall through to string parsing
            }
        }

        $s = trim((string) $v);
        foreach (self::DATE_FORMATS as $fmt) {
            // '!' resets unspecified fields to 00:00:00 so a date-only cell is
            // deterministic (midnight) instead of inheriting the current time.
            $dt  = \DateTime::createFromFormat('!' . $fmt, $s);
            $err = \DateTime::getLastErrors();
            // Accept only an EXACT match: no warnings (trailing data) and no
            // errors. getLastErrors() returns false in PHP 8.2+ when clean.
            $clean = $err === false || (($err['warning_count'] ?? 0) === 0 && ($err['error_count'] ?? 0) === 0);
            if ($dt !== false && $clean) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        // No strtotime fallback on purpose: it reads slash dates as US m/d/Y and
        // silently corrupts d/m/Y values (13/07/26 -> 2027-01-07).
        return null;
    }
}
