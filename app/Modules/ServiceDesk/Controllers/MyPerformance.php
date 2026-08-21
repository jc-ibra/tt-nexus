<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Agent self-view. Two faces of the same story, both scoped to the logged-in
 * user and served through read-only bridges:
 *  - "Mi desempeño": confirmed deviations + escalations (HelpdeskSupervisor).
 *  - "Mis evaluaciones": the monthly KPI evaluation (AgentKpis), which the
 *    supervisor captures in its own module and the agent only reads here.
 *
 * The deviations view needs a GLPI mapping (glpi_user_id) to have anything to
 * show; the evaluations view is keyed on the Nexus user id, which the bridge
 * takes from the session so an id in the URL can never widen the scope.
 */
class MyPerformance extends BaseController
{
    public function index(): string
    {
        $userId = (int) session()->get('user_id');
        $user   = \Config\Database::connect()->table('core_users')
            ->select('name, glpi_user_id')->where('id', $userId)->get()->getRow();

        $glpiUserId = (int) ($user->glpi_user_id ?? 0);

        if ($glpiUserId <= 0) {
            return view('App\Modules\ServiceDesk\Views\my_performance', [
                'pageTitle'  => 'Mi desempeño',
                'available'  => false,
                'periods'    => [],
                'escalations' => [],
                'latestEval' => service('agentKpisBridge')->latestForAgent($userId),
            ]);
        }

        $bridge = service('helpdeskBridge');

        return view('App\Modules\ServiceDesk\Views\my_performance', [
            'pageTitle'   => 'Mi desempeño',
            'available'   => true,
            'agentName'   => (string) ($user->name ?? ''),
            'periods'     => $bridge->confirmedDeviationsForAgent($glpiUserId),
            'escalations' => $bridge->validEscalationsForAgent($glpiUserId),
            'latestEval'  => service('agentKpisBridge')->latestForAgent($userId),
        ]);
    }

    /** List of the agent's own closed monthly evaluations. */
    public function evaluations(): string
    {
        $userId = (int) session()->get('user_id');

        return view('App\Modules\ServiceDesk\Views\my_evaluations', [
            'pageTitle'   => 'Mis evaluaciones',
            'evaluations' => service('agentKpisBridge')->publishedForAgent($userId),
        ]);
    }

    /** Detail of one evaluation: KPIs, rubric, final score and right of reply. */
    public function evaluation(int $id): string
    {
        $userId = (int) session()->get('user_id');
        $bridge = service('agentKpisBridge');

        $eval = $bridge->evaluationForAgent($userId, $id);
        if ($eval === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\ServiceDesk\Views\my_evaluation_detail', [
            'pageTitle' => 'Mi evaluación',
            'eval'      => $eval,
            'snapshots' => $bridge->snapshotsForAgent($userId, $id),
            'rubric'    => $bridge->rubricForAgent($userId, $id),
            'levels'    => \App\Modules\AgentKpis\Config\AgentKpis::LEVELS,
        ]);
    }

    /** Right of reply: the agent records their own comments on their evaluation. */
    public function saveComments(int $id): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $result = service('agentKpisBridge')->saveAgentComments(
            $userId,
            $id,
            (string) $this->request->getPost('agent_comments'),
        );

        return redirect()->to(route_to('servicedesk.myevaluations.show', $id))
            ->with($result->success ? 'success' : 'error', $result->message);
    }
}
