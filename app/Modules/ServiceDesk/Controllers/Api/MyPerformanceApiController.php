<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API v1 mirror of the agent self-view. Every endpoint resolves the agent from
 * the token's own user (ApiAuthFilter puts it in the session), so the caller
 * cannot ask for somebody else's evaluations.
 */
class MyPerformanceApiController extends BaseApiController
{
    /** GET /api/v1/servicedesk/me/performance */
    public function performance(): ResponseInterface
    {
        $userId = $this->userId();
        $row    = \Config\Database::connect()->table('core_users')
            ->select('name, glpi_user_id')->where('id', $userId)->get()->getRow();

        $glpiUserId = (int) ($row->glpi_user_id ?? 0);
        if ($glpiUserId <= 0) {
            return $this->success([
                'available'   => false,
                'periods'     => [],
                'escalations' => [],
            ]);
        }

        $bridge = service('helpdeskBridge');

        return $this->success([
            'available'   => true,
            'agent_name'  => (string) ($row->name ?? ''),
            'periods'     => $bridge->confirmedDeviationsForAgent($glpiUserId),
            'escalations' => $bridge->validEscalationsForAgent($glpiUserId),
        ]);
    }

    /** GET /api/v1/servicedesk/me/evaluations */
    public function index(): ResponseInterface
    {
        return $this->success(service('agentKpisBridge')->publishedForAgent($this->userId()));
    }

    /** GET /api/v1/servicedesk/me/evaluations/{id} */
    public function showEvaluation(int $id): ResponseInterface
    {
        $userId = $this->userId();
        $bridge = service('agentKpisBridge');

        $eval = $bridge->evaluationForAgent($userId, $id);
        if ($eval === null) {
            return $this->notFound('Evaluación no disponible.');
        }

        return $this->success([
            'evaluation' => $eval,
            'snapshots'  => $bridge->snapshotsForAgent($userId, $id),
            'rubric'     => $bridge->rubricForAgent($userId, $id),
        ]);
    }

    /** POST /api/v1/servicedesk/me/evaluations/{id}/comments */
    public function saveComments(int $id): ResponseInterface
    {
        $body   = $this->request->getJSON(true) ?? [];
        $result = service('agentKpisBridge')->saveAgentComments(
            $this->userId(),
            $id,
            (string) ($body['agent_comments'] ?? $this->request->getPost('agent_comments') ?? ''),
        );

        if (! $result->success) {
            return $this->error($result->message, 422);
        }
        return $this->success(['message' => $result->message]);
    }

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }
}
