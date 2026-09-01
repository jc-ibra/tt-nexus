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
    // Live overview
    // ------------------------------------------------------------------

    public function overview(): ResponseInterface
    {
        $force = $this->request->getGet('refresh') === '1' || $this->request->getGet('refresh') === 'true';
        $mode  = $this->request->getGet('mode') === 'period' ? 'period' : 'backlog';
        $start = (string) $this->request->getGet('period_start');
        $end   = (string) $this->request->getGet('period_end');
        if ($mode === 'period') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                $start = date('Y-m-01');
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $end = date('Y-m-t');
            }
        }

        $result = service('helpdeskGlpiOverview')->build(
            $force,
            $mode,
            $mode === 'period' ? $start : null,
            $mode === 'period' ? $end : null,
        );

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message, 503);
    }

    public function overviewTickets(): ResponseInterface
    {
        $dimension = (string) $this->request->getGet('dimension');
        $filterId  = (int) $this->request->getGet('id');
        $mode      = $this->request->getGet('mode') === 'period' ? 'period' : 'backlog';
        $start     = (string) $this->request->getGet('period_start');
        $end       = (string) $this->request->getGet('period_end');
        $page      = max(1, (int) $this->request->getGet('page'));
        $perPage   = max(1, min(200, (int) ($this->request->getGet('per_page') ?: 50)));
        if ($mode === 'period') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                $start = date('Y-m-01');
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $end = date('Y-m-t');
            }
        }

        $overview = service('helpdeskGlpiOverview');
        $result   = $overview->listTickets(
            $dimension,
            $filterId,
            $mode,
            $mode === 'period' ? $start : null,
            $mode === 'period' ? $end : null,
            $page,
            $perPage,
        );

        if (! $result->success) {
            return $this->error($result->message, 503);
        }

        $data = is_array($result->data) ? $result->data : [];
        $data['glpi_portal_url'] = $overview->glpiPortalUrl();

        return $this->success($data);
    }

    public function overviewTicketsExport(): ResponseInterface
    {
        $dimension = (string) $this->request->getGet('dimension');
        $filterId  = (int) $this->request->getGet('id');
        $mode      = $this->request->getGet('mode') === 'period' ? 'period' : 'backlog';
        $start     = (string) $this->request->getGet('period_start');
        $end       = (string) $this->request->getGet('period_end');
        $format    = strtolower((string) $this->request->getGet('format'));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }
        if ($mode === 'period') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                $start = date('Y-m-01');
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $end = date('Y-m-t');
            }
        }

        $overview = service('helpdeskGlpiOverview');
        $result   = $overview->listTicketsForExport(
            $dimension,
            $filterId,
            $mode,
            $mode === 'period' ? $start : null,
            $mode === 'period' ? $end : null,
        );

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        $tickets  = is_array($result->data) ? ($result->data['tickets'] ?? []) : [];
        $portal   = $overview->glpiPortalUrl();
        $exporter = new \App\Modules\HelpdeskSupervisor\Services\OverviewTicketExportService();
        $filename = 'tickets_' . date('Y-m-d_His');

        if ($format === 'xlsx') {
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"')
                ->setBody($exporter->toXlsx($tickets, $portal));
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $exporter->toCsv($tickets, $portal));
    }

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
        $model   = new DeviationModel();
        $runId   = (int) $runId;
        $ruleKey = (string) $ruleKey;
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPage = max(1, min(200, (int) ($this->request->getGet('per_page') ?: 50)));
        $total   = $model->countForRule($runId, $ruleKey);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page    = min($page, $lastPage);

        return $this->success([
            'deviations'  => $model->forRulePaginated($runId, $ruleKey, $page, $perPage),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $lastPage,
        ]);
    }

    public function ruleDeviationsExport($runId = null, $ruleKey = null): ResponseInterface
    {
        $model    = new DeviationModel();
        $runId    = (int) $runId;
        $ruleKey  = (string) $ruleKey;
        $format   = strtolower((string) $this->request->getGet('format'));
        $maxRows  = 50000;
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $total = $model->countForRule($runId, $ruleKey);
        if ($total > $maxRows) {
            return $this->error(
                'Hay más de ' . number_format($maxRows) . ' incumplimientos. Acota el filtro antes de exportar.',
                422,
            );
        }

        $rows     = $model->forRuleExport($runId, $ruleKey, $maxRows);
        $portal   = $this->glpiPortalUrl();
        $exporter = new \App\Modules\HelpdeskSupervisor\Services\DeviationExportService();
        $filename = 'desviaciones_' . $ruleKey . '_' . date('Y-m-d_His');

        if ($format === 'xlsx') {
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"')
                ->setBody($exporter->toXlsx($rows, $portal));
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $exporter->toCsv($rows, $portal));
    }

    private function glpiPortalUrl(): string
    {
        try {
            $s = service('provisioningSettings')->getAll();
            $url = trim((string) ($s['glpi_url'] ?? $s['glpi_base_url'] ?? ''));
            if ($url !== '') {
                return rtrim($url, '/');
            }
        } catch (\Throwable) {
            // fall through
        }

        return 'https://helpdesk.trantortechnologies.mx';
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
