<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\ServiceDesk\Models\ServiceDeskAssignmentModel;
use App\Modules\ServiceDesk\Services\AssignmentMatrixImporter;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API v1 mirror of the assignment matrix.
 *
 * Reading is open to any token that can reach the module, the same as the web
 * page. Replacing the matrix and mapping people to users are SuperAdmin-only,
 * mirroring the /admin route group the web actions live under; the route filter
 * only checks module access, so the check is made here.
 */
class AssignmentsApiController extends BaseApiController
{
    /** GET /api/v1/servicedesk/assignments */
    public function index(): ResponseInterface
    {
        $model = new ServiceDeskAssignmentModel();
        $mine  = $model->agentForUser((int) session()->get('user_id'));

        return $this->success([
            'agents'     => $model->agents(),
            'stages'     => AssignmentMatrixImporter::STAGES,
            'legend'     => service('assignmentMatrixImporter')->legend(),
            'my_agent'   => $mine,
            'categories' => $model->matrix(),
            'updated_at' => service('serviceDeskSettings')->all()[AssignmentMatrixImporter::KEY_UPDATED] ?? null,
        ]);
    }

    /** GET /api/v1/servicedesk/assignments/agents */
    public function agents(): ResponseInterface
    {
        return $this->success((new ServiceDeskAssignmentModel())->agents());
    }

    /**
     * POST /api/v1/servicedesk/assignments
     * multipart/form-data with `file`: replaces the whole matrix.
     */
    public function upload(): ResponseInterface
    {
        if (($denied = $this->requireSuperAdmin()) !== null) {
            return $denied;
        }

        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid()) {
            return $this->error('Envía el archivo .xlsx en el campo `file`.');
        }
        if (! in_array(strtolower($file->getExtension()), ['xlsx', 'xlsm'], true)) {
            return $this->error('El archivo debe ser .xlsx.');
        }

        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        $tmpName = 'assign_api_' . bin2hex(random_bytes(6)) . '.xlsx';
        $file->move($tmpDir, $tmpName);

        $result = service('assignmentMatrixImporter')->import(
            $tmpDir . '/' . $tmpName,
            $file->getClientName(),
            (int) session()->get('user_id')
        );
        @unlink($tmpDir . '/' . $tmpName);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success([
            'message'    => $result->message,
            'agents'     => $result->data['agents'],
            'categories' => $result->data['categories'],
            'cells'      => count($result->data['cells']),
        ]);
    }

    /**
     * POST /api/v1/servicedesk/assignments/agents
     * body: { "agent_user": { "<agent_id>": <user_id>, ... } } — 0 unlinks.
     */
    public function saveAgents(): ResponseInterface
    {
        if (($denied = $this->requireSuperAdmin()) !== null) {
            return $denied;
        }

        $input = $this->request->getJSON(true) ?? $this->request->getPost();
        $map   = (array) ($input['agent_user'] ?? []);
        if ($map === []) {
            return $this->error('Envía `agent_user` como un objeto id_de_persona => id_de_usuario.');
        }

        $clean = [];
        foreach ($map as $agentId => $userId) {
            $clean[(int) $agentId] = (int) $userId;
        }

        $model = new ServiceDeskAssignmentModel();
        $model->saveAgentUsers($clean);

        return $this->success(['agents' => $model->agents()]);
    }

    /** 403 unless the token's user is a SuperAdmin. */
    private function requireSuperAdmin(): ?ResponseInterface
    {
        if (service('access')->isSuperAdmin()) {
            return null;
        }
        return $this->error('Solo un SuperAdmin puede modificar la matriz de asignaciones.', 403);
    }
}
