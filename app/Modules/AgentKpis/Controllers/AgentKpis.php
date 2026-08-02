<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Controllers;

use App\Controllers\BaseController;
use App\Modules\AgentKpis\Config\AgentKpis as RubricConfig;
use App\Modules\AgentKpis\Models\KpiSnapshotModel;
use App\Modules\AgentKpis\Models\MonthlyEvaluationModel;
use CodeIgniter\HTTP\RedirectResponse;

class AgentKpis extends BaseController
{
    private MonthlyEvaluationModel $evaluations;

    public function __construct()
    {
        $this->evaluations = new MonthlyEvaluationModel();
    }

    public function index(): string
    {
        [$year, $month] = $this->period();
        $hasRun = service('helpdeskBridge')->getLatestRun($year, $month) !== null;

        return view('App\Modules\AgentKpis\Views\dashboard', [
            'pageTitle'   => 'Evaluación de Agentes',
            'year'        => $year,
            'month'       => $month,
            'evaluations' => $this->evaluations->forMonth($year, $month),
            'hasRun'      => $hasRun,
        ]);
    }

    public function generate(): RedirectResponse
    {
        $year  = (int) $this->request->getPost('period_year');
        $month = (int) $this->request->getPost('period_month');
        if ($year < 2020 || $month < 1 || $month > 12) {
            return redirect()->back()->with('error', 'Mes inválido.');
        }

        $uid    = session()->get('user_id');
        $result = service('agentKpisCalculation')->generateForMonth($year, $month, $uid !== null ? (int) $uid : null);

        $to = route_to('agentkpis.index') . '?year=' . $year . '&month=' . $month;
        return redirect()->to($to)->with($result->success ? 'success' : 'error', $result->message);
    }

    public function show(int $id): string
    {
        $eval = $this->evaluations->find($id);
        if ($eval === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\AgentKpis\Views\evaluation_detail', [
            'pageTitle' => 'Evaluación · ' . $eval['agent_name'],
            'eval'      => $eval,
            'snapshots' => (new KpiSnapshotModel())->forEvaluation($id),
            'rubric'    => service('agentKpisQualitative')->getRubric($id),
        ]);
    }

    public function qualitative(int $id): string
    {
        $eval = $this->evaluations->find($id);
        if ($eval === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\AgentKpis\Views\qualitative_form', [
            'pageTitle' => 'Rúbrica · ' . $eval['agent_name'],
            'eval'      => $eval,
            'rubric'    => service('agentKpisQualitative')->getRubric($id),
            'levels'    => RubricConfig::LEVELS,
        ]);
    }

    public function saveQualitative(int $id): RedirectResponse
    {
        $scores   = (array) ($this->request->getPost('score') ?? []);
        $evidence = (array) ($this->request->getPost('evidence') ?? []);
        $uid      = session()->get('user_id');

        $result = service('agentKpisQualitative')->saveRubric($id, $scores, $evidence, $uid !== null ? (int) $uid : null);
        return redirect()->to(route_to('agentkpis.show', $id))->with($result->success ? 'success' : 'error', $result->message);
    }

    public function saveNotes(int $id): RedirectResponse
    {
        service('agentKpisQualitative')->saveNotes(
            $id,
            $this->request->getPost('supervisor_notes'),
            $this->request->getPost('agent_comments'),
        );
        return redirect()->to(route_to('agentkpis.show', $id))->with('success', 'Notas guardadas.');
    }

    public function agentHistory(int $nexusUserId): string
    {
        $rows = $this->evaluations->historyForAgent($nexusUserId);
        return view('App\Modules\AgentKpis\Views\agent_history', [
            'pageTitle' => 'Historial del agente',
            'rows'      => $rows,
            'agentName' => $rows !== [] ? (string) end($rows)['agent_name'] : '',
        ]);
    }

    public function history(): string
    {
        return view('App\Modules\AgentKpis\Views\history', [
            'pageTitle'   => 'Historial de evaluaciones',
            'evaluations' => $this->evaluations->orderBy('period_year', 'DESC')->orderBy('period_month', 'DESC')->orderBy('agent_name', 'ASC')->findAll(300),
        ]);
    }

    /** Selected period from GET, defaulting to the current month. */
    private function period(): array
    {
        $year  = (int) ($this->request->getGet('year') ?: date('Y'));
        $month = (int) ($this->request->getGet('month') ?: date('n'));
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        return [$year, $month];
    }
}
