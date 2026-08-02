<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\AgentKpis\Models\KpiSnapshotModel;
use App\Modules\AgentKpis\Models\MonthlyEvaluationModel;
use CodeIgniter\HTTP\ResponseInterface;

class AgentKpisApiController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $year  = (int) ($this->request->getGet('year') ?: date('Y'));
        $month = (int) ($this->request->getGet('month') ?: date('n'));
        return $this->success((new MonthlyEvaluationModel())->forMonth($year, $month));
    }

    public function generate(): ResponseInterface
    {
        $body  = (array) $this->request->getJSON(true);
        $year  = (int) ($body['period_year'] ?? 0);
        $month = (int) ($body['period_month'] ?? 0);
        if ($year < 2020 || $month < 1 || $month > 12) {
            return $this->error('period_year y period_month son obligatorios.', 422);
        }
        $uid    = session()->get('user_id');
        $result = service('agentKpisCalculation')->generateForMonth($year, $month, $uid !== null ? (int) $uid : null);
        return $result->success ? $this->success($result->data, $result->message) : $this->error($result->message, 422);
    }

    public function show($id = null): ResponseInterface
    {
        $eval = (new MonthlyEvaluationModel())->find((int) $id);
        if ($eval === null) {
            return $this->notFound('Evaluación no encontrada.');
        }
        return $this->success([
            'evaluation' => $eval,
            'snapshots'  => (new KpiSnapshotModel())->forEvaluation((int) $id),
            'rubric'     => service('agentKpisQualitative')->getRubric((int) $id),
        ]);
    }

    public function saveQualitative($id = null): ResponseInterface
    {
        $body     = (array) $this->request->getJSON(true);
        $scores   = (array) ($body['score'] ?? []);
        $evidence = (array) ($body['evidence'] ?? []);
        $uid      = session()->get('user_id');
        $result   = service('agentKpisQualitative')->saveRubric((int) $id, $scores, $evidence, $uid !== null ? (int) $uid : null);
        return $result->success ? $this->success($result->data, $result->message) : $this->error($result->message, 422);
    }

    public function agentHistory($nexusUserId = null): ResponseInterface
    {
        return $this->success((new MonthlyEvaluationModel())->historyForAgent((int) $nexusUserId));
    }
}
