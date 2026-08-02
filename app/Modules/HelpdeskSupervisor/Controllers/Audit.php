<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use CodeIgniter\HTTP\RedirectResponse;

class Audit extends BaseController
{
    /** Runs an audit for a period (optionally a single agent), then returns to the dashboard. */
    public function run(): RedirectResponse
    {
        $start = (string) $this->request->getPost('period_start');
        $end   = (string) $this->request->getPost('period_end');
        $agent = (int) $this->request->getPost('agent_glpi_user_id');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return redirect()->back()->with('error', 'Período inválido.');
        }
        if ($start > $end) {
            return redirect()->back()->with('error', 'La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        $uid    = session()->get('user_id');
        $result = service('helpdeskAuditRunner')->run($start, $end, $agent > 0 ? $agent : null, $uid !== null ? (int) $uid : null);

        $to = route_to('helpdesk.index') . '?period_start=' . $start . '&period_end=' . $end;

        if (! $result->success) {
            return redirect()->to($to)->with('error', $result->message);
        }
        return redirect()->to($to)->with('success', $result->message);
    }

    public function runs(): string
    {
        return view('App\Modules\HelpdeskSupervisor\Views\audit_runs', [
            'pageTitle' => 'Historial de auditorías',
            'runs'      => (new AuditRunModel())->recent(100),
        ]);
    }

    public function showRun(int $id): string
    {
        $run = (new AuditRunModel())->find($id);
        if ($run === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $deviations = new DeviationModel();

        return view('App\Modules\HelpdeskSupervisor\Views\audit_run_detail', [
            'pageTitle' => 'Auditoría #' . $id,
            'run'       => $run,
            'agents'    => $deviations->agentSummary($id),
            'rules'     => $deviations->ruleSummary($id),
        ]);
    }
}
