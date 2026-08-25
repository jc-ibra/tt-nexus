<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use App\Modules\ServiceDesk\Models\ServiceDeskAssignmentModel;
use App\Modules\ServiceDesk\Services\AssignmentMatrixImporter;

/**
 * Read-only view of the assignment matrix for the whole Service Desk team.
 *
 * Everyone with access to the module sees the same matrix; what changes per
 * user is the "solo lo mío" shortcut, which needs the SuperAdmin to have mapped
 * that person to a name in the sheet. The file itself is never handed out.
 */
class Assignments extends BaseController
{
    public function index(): string
    {
        $model  = new ServiceDeskAssignmentModel();
        $mine   = $model->agentForUser((int) session()->get('user_id'));
        $config = service('serviceDeskSettings')->all();

        return view('App\Modules\ServiceDesk\Views\assignments', [
            'pageTitle'   => 'Asignaciones',
            'agents'      => $model->agents(),
            'matrix'      => $model->matrix(),
            'stages'      => array_keys(AssignmentMatrixImporter::STAGES),
            'legend'      => service('assignmentMatrixImporter')->legend(),
            'myAgentId'   => $mine['id'] ?? null,
            'myAgentName' => $mine['name'] ?? '',
            'updatedAt'   => $config[AssignmentMatrixImporter::KEY_UPDATED] ?? '',
        ]);
    }
}
