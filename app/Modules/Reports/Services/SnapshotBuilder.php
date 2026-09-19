<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Models\ReportSnapshotModel;
use App\Modules\Reports\Models\ReportSnapshotVersionModel;
use App\Modules\Reports\Services\Providers\ReportSectionProvider;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the monthly snapshot: runs every registered provider, merges
 * their sections into one payload, and freezes it into reports_snapshots.
 * A provider that is unavailable degrades its own section instead of
 * failing the whole month; a provider that throws is caught the same way,
 * so one broken data source never blocks the other four.
 */
class SnapshotBuilder
{
    /** @param ReportSectionProvider[] $providers */
    public function __construct(
        private ReportSnapshotModel $snapshots,
        private ReportSnapshotVersionModel $versions,
        private array $providers,
    ) {}

    /**
     * Generates (or regenerates) the frozen snapshot for a natural month.
     *
     * @throws RuntimeException when the month is already ready and $force is false
     */
    public function generate(int $year, int $month, ?int $userId = null, bool $force = false): array
    {
        $period   = new ReportPeriod($year, $month);
        $existing = $this->snapshots->findByPeriod($year, $month);

        if ($existing !== null && $existing['status'] === 'ready' && ! $force) {
            throw new RuntimeException("El informe de {$period->label} ya está generado. Use regenerar para reemplazarlo.");
        }

        $snapshotId = $existing['id'] ?? null;
        if ($snapshotId === null) {
            $snapshotId = $this->snapshots->insert([
                'period_year'  => $year,
                'period_month' => $month,
                'period_start' => $period->startDate,
                'period_end'   => $period->endDate,
                'status'       => 'processing',
                'generated_by' => $userId,
            ], true);
        } else {
            // Regeneration: archive the current payload before overwriting it.
            if ($existing['payload_json'] !== null) {
                $this->versions->insert([
                    'snapshot_id'   => $snapshotId,
                    'payload_json'  => $existing['payload_json'],
                    'total_tickets' => $existing['total_tickets'],
                    'replaced_by'   => $userId,
                ]);
            }
            // The old export files describe the PREVIOUS payload; clearing
            // their paths forces Reports::pptx()/xlsx() to re-render from the
            // new one instead of silently reusing stale files. Best-effort
            // delete the files themselves too, so disk doesn't accumulate them.
            foreach ([$existing['pptx_path'], $existing['xlsx_path']] as $oldPath) {
                if ($oldPath && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $this->snapshots->update($snapshotId, [
                'status'         => 'processing',
                'error_message'  => null,
                'regenerated_at' => $existing['status'] === 'ready' ? date('Y-m-d H:i:s') : null,
                'regenerated_by' => $existing['status'] === 'ready' ? $userId : null,
                'pptx_path'      => null,
                'xlsx_path'      => null,
            ]);
        }

        try {
            $payload  = [];
            $sections = [];
            foreach ($this->providers as $provider) {
                $key = $provider->key();
                try {
                    $payload[$key] = $provider->isAvailable()
                        ? $provider->collect($period)
                        : ['available' => false];
                } catch (Throwable $e) {
                    log_message('error', "[Reports] provider '{$key}' failed: " . $e->getMessage());
                    $payload[$key] = ['available' => false, 'error' => $e->getMessage()];
                }
                $sections[] = $key;
            }

            $totalTickets = (int) ($payload['glpi_tickets']['total'] ?? 0);

            $this->snapshots->update($snapshotId, [
                'status'        => 'ready',
                'sections'      => json_encode($sections),
                'payload_json'  => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'total_tickets' => $totalTickets,
            ]);
        } catch (Throwable $e) {
            $this->snapshots->update($snapshotId, [
                'status'        => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);
            throw $e;
        }

        return $this->snapshots->find($snapshotId);
    }
}
