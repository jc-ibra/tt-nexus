<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API mirror of the RRHH analytics panel. Read-only.
 */
class EmployeeDashboardApiController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->success(service('employeeDashboardService')->snapshot());
    }
}
