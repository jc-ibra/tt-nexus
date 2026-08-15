<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;

/**
 * RRHH analytics panel. Read-only: it renders aggregates of the directory and
 * exposes no write action of any kind.
 */
class EmployeeDashboard extends BaseController
{
    public function index(): string
    {
        $svc      = service('employeeDashboardService');
        $snapshot = $svc->snapshot();

        return view('App\Modules\Employees\Views\employees\dashboard', [
            'pageTitle' => 'Panel de empleados',
            'snapshot'  => $snapshot,
            // Long-tail dimensions are folded into an "Otros" bucket for the
            // charts; the full lists stay available in the snapshot.
            'areaChart'       => $svc->topWithOthers($snapshot['by_area'], 10),
            'departmentChart' => $svc->topWithOthers($snapshot['by_department'], 12),
            'positionChart'   => $svc->topWithOthers($snapshot['by_position'], 10),
            'stateChart'      => $svc->topWithOthers($snapshot['by_state'], 12),
            'locationChart'   => $svc->topWithOthers($snapshot['by_location'], 8),
        ]);
    }
}
