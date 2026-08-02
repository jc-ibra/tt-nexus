<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Models\EscalationModel;
use App\Modules\HelpdeskSupervisor\Models\NotificationModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * JSON mirror of the HelpdeskSupervisor web actions. Standard envelope via
 * BaseApiController.
 */
class HelpdeskSupervisorApiController extends BaseApiController
{
    // ------------------------------------------------------------------
    // Audit
    // ------------------------------------------------------------------

    public function runAudit(): ResponseInterface
    {
        $body  = (array) $this->request->getJSON(true);
        $start = (string) ($body['period_start'] ?? '');
        $end   = (string) ($body['period_end'] ?? '');
        $agent = (int) ($body['agent_glpi_user_id'] ?? 0);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return $this->error('period_start y period_end deben tener formato YYYY-MM-DD.', 422);
        }

        $uid    = session()->get('user_id');
        $result = service('helpdeskAuditRunner')->run($start, $end, $agent > 0 ? $agent : null, $uid !== null ? (int) $uid : null);

        return $result->success
            ? $this->success($result->data, $result->message)
            : $this->error($result->message, 422);
    }

    public function runs(): ResponseInterface
    {
        return $this->success((new AuditRunModel())->recent(100));
    }

    public function showRun($id = null): ResponseInterface
    {
        $run = (new AuditRunModel())->find((int) $id);
        if ($run === null) {
            return $this->notFound('Auditoría no encontrada.');
        }
        $dev = new DeviationModel();
        return $this->success([
            'run'    => $run,
            'agents' => $dev->agentSummary((int) $id),
            'rules'  => $dev->ruleSummary((int) $id),
        ]);
    }

    public function agents($runId = null): ResponseInterface
    {
        return $this->success((new DeviationModel())->agentSummary((int) $runId));
    }

    public function agentDeviations($runId = null, $glpiUserId = null): ResponseInterface
    {
        return $this->success((new DeviationModel())->forAgent((int) $runId, (int) $glpiUserId));
    }

    public function ruleDeviations($runId = null, $ruleKey = null): ResponseInterface
    {
        return $this->success((new DeviationModel())->forRule((int) $runId, (string) $ruleKey));
    }

    // ------------------------------------------------------------------
    // Escalations
    // ------------------------------------------------------------------

    public function escalationsIndex(): ResponseInterface
    {
        return $this->success((new EscalationModel())->orderBy('escalation_date', 'DESC')->findAll(200));
    }

    public function escalationsCreate(): ResponseInterface
    {
        $model = new EscalationModel();
        $data  = (array) $this->request->getJSON(true);
        if (! $model->insert($data)) {
            return $this->validationError($model->errors());
        }
        return $this->successCreated($model->find($model->getInsertID()));
    }

    public function escalationsUpdate($id = null): ResponseInterface
    {
        $model = new EscalationModel();
        if ($model->find((int) $id) === null) {
            return $this->notFound('Escalación no encontrada.');
        }
        $data = (array) $this->request->getJSON(true);
        if (! $model->update((int) $id, $data)) {
            return $this->validationError($model->errors());
        }
        return $this->success($model->find((int) $id));
    }

    public function escalationsDelete($id = null): ResponseInterface
    {
        (new EscalationModel())->delete((int) $id);
        return $this->response->setStatusCode(204)->setBody('');
    }

    // ------------------------------------------------------------------
    // Notifications (Fase 2)
    // ------------------------------------------------------------------

    public function notificationsIndex(): ResponseInterface
    {
        return $this->success((new NotificationModel())->recent(150));
    }

    public function notificationPrepare(): ResponseInterface
    {
        $body  = (array) $this->request->getJSON(true);
        $runId = (int) ($body['audit_run_id'] ?? 0);
        $glpi  = (int) ($body['glpi_user_id'] ?? 0);

        if ($runId <= 0 && isset($body['period_start'], $body['period_end'])) {
            $run   = (new AuditRunModel())->latestCompletedForPeriod((string) $body['period_start'], (string) $body['period_end']);
            $runId = $run ? (int) $run['id'] : 0;
        }
        if ($runId <= 0 || $glpi <= 0) {
            return $this->error('Se requiere audit_run_id (o period_start/period_end) y glpi_user_id.', 422);
        }

        $result = service('helpdeskNotificationSender')->prepare($runId, $glpi, (string) ($body['supervisor_name'] ?? ''));
        return $result->success ? $this->success($result->data, $result->message) : $this->error($result->message, 422);
    }

    public function notificationSend($id = null): ResponseInterface
    {
        $body = (array) $this->request->getJSON(true);
        $uid  = session()->get('user_id');
        $result = service('helpdeskNotificationSender')->send((int) $id, $body, $uid !== null ? (int) $uid : null);
        return $result->success ? $this->success($result->data, $result->message) : $this->error($result->message, 422);
    }

    public function notificationDelete($id = null): ResponseInterface
    {
        (new NotificationModel())->delete((int) $id);
        return $this->response->setStatusCode(204)->setBody('');
    }
}
