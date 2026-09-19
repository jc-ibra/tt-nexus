<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Providers;

use App\Modules\Reports\Services\ReportPeriod;

/**
 * A section of the monthly report. Each provider owns one data source and
 * returns a plain, JSON-serializable array — SnapshotBuilder never inspects
 * the contents, it only merges collect() results under their section key
 * into reports_snapshots.payload_json.
 *
 * isAvailable() lets a provider degrade gracefully (module not configured,
 * connection down) instead of failing the whole month's generation: when it
 * returns false, SnapshotBuilder stores ['available' => false] for that
 * section without calling collect().
 */
interface ReportSectionProvider
{
    /** Section key, matches Config\Reports::$sections. */
    public function key(): string;

    public function label(): string;

    public function isAvailable(): bool;

    /** @return array<string,mixed> */
    public function collect(ReportPeriod $period): array;
}
