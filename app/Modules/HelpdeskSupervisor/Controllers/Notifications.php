<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Models\NotificationModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Fase 2: per-agent IA notifications (draft, review/edit, send with Excel).
 */
class Notifications extends BaseController
{
    private NotificationModel $model;

    public function __construct()
    {
        $this->model = new NotificationModel();
    }

    /** Prepares a draft for one agent (glpi_user_id) for the posted period's run. */
    public function prepare(int $glpiUserId): RedirectResponse
    {
        $run = $this->runForPostedPeriod();
        if ($run === null) {
            return redirect()->back()->with('error', 'No hay una auditoría completada para el período.');
        }

        $result = service('helpdeskNotificationSender')->prepare((int) $run['id'], $glpiUserId, $this->supervisorName());
        if (! $result->success) {
            return redirect()->back()->with('error', $result->message);
        }

        $id = (int) $result->data['notification_id'];
        return redirect()->to(route_to('helpdesk.notifications.review', $id))->with($result->data['ai_ok'] ? 'success' : 'error', $result->message);
    }

    /** Prepares drafts for every agent with deviations in the posted period's run. */
    public function prepareAll(): RedirectResponse
    {
        $run = $this->runForPostedPeriod();
        if ($run === null) {
            return redirect()->back()->with('error', 'No hay una auditoría completada para el período.');
        }

        $agents = (new DeviationModel())->agentSummary((int) $run['id']);
        $ok = 0;
        foreach ($agents as $a) {
            $res = service('helpdeskNotificationSender')->prepare((int) $run['id'], (int) $a['glpi_user_id'], $this->supervisorName());
            if ($res->success) {
                $ok++;
            }
        }

        return redirect()->to(route_to('helpdesk.notifications.index'))
            ->with('success', "Se prepararon {$ok} borrador(es). Revísalos y envíalos.");
    }

    public function index(): string
    {
        return view('App\Modules\HelpdeskSupervisor\Views\notifications\index', [
            'pageTitle'     => 'Notificaciones',
            'notifications' => $this->model->recent(150),
        ]);
    }

    public function review(int $id): string
    {
        $n = $this->model->find($id);
        if ($n === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $defaultSubject = 'Reporte de desviaciones - '
            . date('d/m/Y', strtotime((string) $n['period_start'])) . ' a '
            . date('d/m/Y', strtotime((string) $n['period_end'])) . ' - ' . $n['agent_name'];

        return view('App\Modules\HelpdeskSupervisor\Views\notifications\review', [
            'pageTitle'      => 'Revisar notificación',
            'n'              => $n,
            'defaultSubject' => $defaultSubject,
            'cc'             => implode(', ', service('helpdeskSupervisorSettings')->notificationCc()),
            'excelName'      => $n['excel_path'] ? basename((string) $n['excel_path']) : '',
        ]);
    }

    public function regenerate(int $id): RedirectResponse
    {
        $n = $this->model->find($id);
        if ($n === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $result = service('helpdeskNotificationSender')->prepare((int) $n['audit_run_id'], (int) $n['glpi_user_id'], $this->supervisorName());
        return redirect()->to(route_to('helpdesk.notifications.review', $id))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function send(int $id): RedirectResponse
    {
        $overrides = [
            'to'         => trim((string) $this->request->getPost('to')),
            'subject'    => trim((string) $this->request->getPost('subject')),
            'final_body' => (string) $this->request->getPost('final_body'),
        ];
        $ccRaw = trim((string) $this->request->getPost('cc'));
        if ($ccRaw !== '') {
            $overrides['cc'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $ccRaw) ?: [])));
        }

        $uid    = session()->get('user_id');
        $result = service('helpdeskNotificationSender')->send($id, $overrides, $uid !== null ? (int) $uid : null);

        if (! $result->success) {
            return redirect()->to(route_to('helpdesk.notifications.review', $id))->with('error', $result->message);
        }
        return redirect()->to(route_to('helpdesk.notifications.index'))->with('success', $result->message);
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->model->delete($id);
        return redirect()->to(route_to('helpdesk.notifications.index'))->with('success', 'Notificación descartada.');
    }

    // ------------------------------------------------------------------

    private function runForPostedPeriod(): ?array
    {
        $start = (string) $this->request->getPost('period_start');
        $end   = (string) $this->request->getPost('period_end');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return null;
        }
        return (new AuditRunModel())->latestCompletedForPeriod($start, $end);
    }

    private function supervisorName(): string
    {
        return (string) (session()->get('name') ?? session()->get('user_name') ?? '');
    }
}
