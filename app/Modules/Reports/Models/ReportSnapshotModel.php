<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use CodeIgniter\Model;

class ReportSnapshotModel extends Model
{
    protected $table         = 'reports_snapshots';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'period_year',
        'period_month',
        'period_start',
        'period_end',
        'status',
        'sections',
        'payload_json',
        'total_tickets',
        'pptx_path',
        'xlsx_path',
        'error_message',
        'generated_by',
        'regenerated_at',
        'regenerated_by',
    ];

    protected $validationRules = [
        'period_year'  => 'required|is_natural_no_zero',
        'period_month' => 'required|is_natural_no_zero|less_than_equal_to[12]',
        'status'       => 'in_list[processing,ready,failed]',
    ];

    public function findByPeriod(int $year, int $month): ?array
    {
        return $this->where('period_year', $year)->where('period_month', $month)->first();
    }

    /** Newest-first list for the landing page, one row per month generated. */
    public function listRecent(int $limit = 24): array
    {
        return $this->orderBy('period_year', 'DESC')->orderBy('period_month', 'DESC')->findAll($limit);
    }

    /** Decoded payload, or null when the snapshot is not ready yet. */
    public function getPayload(int $id): ?array
    {
        $row = $this->select('payload_json')->find($id);
        if (! $row || empty($row['payload_json'])) {
            return null;
        }
        $decoded = json_decode($row['payload_json'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /** The most recent READY snapshot strictly before the given period, for month-over-month deltas. */
    public function findPreviousReady(int $year, int $month): ?array
    {
        return $this->where('status', 'ready')
            ->where('period_start <', sprintf('%04d-%02d-01', $year, $month))
            ->orderBy('period_year', 'DESC')->orderBy('period_month', 'DESC')
            ->first();
    }

    /** The most recent READY snapshot for the same calendar month, a year (or more) back. */
    public function findSameMonthLastYear(int $year, int $month): ?array
    {
        return $this->where('status', 'ready')
            ->where('period_month', $month)
            ->where('period_year', $year - 1)
            ->first();
    }
}
