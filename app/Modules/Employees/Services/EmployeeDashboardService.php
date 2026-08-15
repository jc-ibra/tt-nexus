<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Employees\Models\EmployeeModel;

/**
 * Read-only analytics for the RRHH dashboard.
 *
 * Every method here only reads: it aggregates the directory and never writes,
 * queues or triggers anything. It also deliberately ignores the account and
 * provisioning side of an employee (mailboxes, system accounts, licenses) —
 * that data belongs to Sistemas and has no place in an RRHH panel.
 */
class EmployeeDashboardService
{
    /** Head-count is reported over the ACTIVE population unless stated otherwise. */
    private const ACTIVE_ONLY = true;

    public function __construct(private EmployeeModel $employees) {}

    /**
     * The whole dashboard in one payload, shared by the web view and the API.
     */
    public function snapshot(): array
    {
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'scope'        => 'active',
            'summary'      => $this->employees->headcountSummary(),
            'by_area'       => $this->employees->headcountByDimension('area', self::ACTIVE_ONLY),
            'by_department' => $this->employees->headcountByDimension('department', self::ACTIVE_ONLY),
            'by_position'   => $this->employees->headcountByDimension('position', self::ACTIVE_ONLY),
            'by_state'      => $this->employees->headcountByDimension('state', self::ACTIVE_ONLY),
            'by_location'   => $this->employees->headcountByDimension('location', self::ACTIVE_ONLY),
            'tenure'         => $this->employees->tenureBuckets(),
            'movements'      => $this->employees->movementsByMonth(12),
            'span_of_control' => $this->employees->spanOfControl(10),
            'missing_data'    => $this->employees->missingDataCounts(),
        ];
    }

    /**
     * Cut a distribution down to the `$limit` biggest buckets and fold the tail
     * into a single "Otros" row, so a chart with 60 departments stays readable.
     * The folded row carries a null id: it is not a catalog entry and must not
     * be turned into a drill-down link.
     *
     * @param list<array{id:int|null,name:string,total:int}> $rows
     *
     * @return list<array{id:int|null,name:string,total:int}>
     */
    public function topWithOthers(array $rows, int $limit = 12): array
    {
        if (count($rows) <= $limit) {
            return $rows;
        }

        $top  = array_slice($rows, 0, $limit);
        $tail = array_slice($rows, $limit);

        $top[] = [
            'id'    => null,
            'name'  => 'Otros (' . count($tail) . ')',
            'total' => array_sum(array_column($tail, 'total')),
        ];

        return $top;
    }
}
