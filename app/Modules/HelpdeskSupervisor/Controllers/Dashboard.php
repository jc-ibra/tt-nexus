<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Models\EscalationModel;
use App\Modules\HelpdeskSupervisor\Rules\RuleRegistry;

/**
 * Supervisor dashboard: period selector, global metrics, agent ranking and the
 * top offending rules for the latest completed run of the selected period.
 */
class Dashboard extends BaseController
{
    private AuditRunModel $runs;
    private DeviationModel $deviations;

    public function __construct()
    {
        $this->runs       = new AuditRunModel();
        $this->deviations = new DeviationModel();
    }

    public function index(): string
    {
        [$start, $end] = $this->period();
        $run = $this->runs->latestCompletedForPeriod($start, $end);

        $agents = $run ? $this->deviations->agentSummary((int) $run['id']) : [];
        $rules  = $run ? $this->deviations->ruleSummary((int) $run['id']) : [];

        // Collapse the per-severity rule rows into one row per rule.
        $ruleTotals = [];
        foreach ($rules as $r) {
            $key = $r['rule_key'];
            $ruleTotals[$key]['rule_name'] = $r['rule_name'];
            $ruleTotals[$key]['count'] = ($ruleTotals[$key]['count'] ?? 0) + (int) $r['count'];
        }
        arsort($ruleTotals);

        $totalDeviations = $run ? (int) $run['total_deviations_found'] : 0;
        $totalTickets    = $run ? (int) $run['total_tickets_audited'] : 0;
        $ticketsWithDev  = array_sum(array_map(static fn($a) => (int) $a['tickets_with_deviations'], $agents));
        $compliance      = $totalTickets > 0 ? round((($totalTickets - $ticketsWithDev) / $totalTickets) * 100, 1) : 0.0;

        return view('App\Modules\HelpdeskSupervisor\Views\dashboard', [
            'pageTitle'       => 'Supervisor de Mesa',
            'periodStart'     => $start,
            'periodEnd'       => $end,
            'run'             => $run,
            'agents'          => $agents,
            'ruleTotals'      => $ruleTotals,
            'totalDeviations' => $totalDeviations,
            'totalTickets'    => $totalTickets,
            'compliance'      => $compliance,
            'agentsAudited'   => $run ? (int) $run['total_agents_audited'] : 0,
        ]);
    }

    public function agent(int $glpiUserId): string
    {
        [$start, $end] = $this->period();
        $run = $this->runs->latestCompletedForPeriod($start, $end);
        if ($run === null) {
            return view('App\Modules\HelpdeskSupervisor\Views\agent_detail', [
                'pageTitle'   => 'Detalle de agente',
                'run'         => null,
                'glpiUserId'  => $glpiUserId,
                'periodStart' => $start,
                'periodEnd'   => $end,
                'deviations'  => [],
                'agentName'   => '',
                'escalations' => [],
            ]);
        }

        $deviations = $this->deviations->forAgent((int) $run['id'], $glpiUserId);
        $agentName  = $deviations[0]['agent_name'] ?? '';

        $escalations = (new EscalationModel())->forAgentMonth(
            $glpiUserId,
            (int) substr($start, 0, 4),
            (int) substr($start, 5, 2),
        );

        return view('App\Modules\HelpdeskSupervisor\Views\agent_detail', [
            'pageTitle'   => $agentName !== '' ? $agentName : 'Detalle de agente',
            'run'         => $run,
            'glpiUserId'  => $glpiUserId,
            'periodStart' => $start,
            'periodEnd'   => $end,
            'deviations'  => $deviations,
            'agentName'   => $agentName,
            'escalations' => $escalations,
            'glpiBaseUrl' => $this->glpiBaseUrl(),
        ]);
    }

    /** Marks which of an agent's deviations "proceden" (visible to the agent). */
    public function confirmAgent(int $glpiUserId): \CodeIgniter\HTTP\RedirectResponse
    {
        $start = (string) $this->request->getPost('period_start');
        $end   = (string) $this->request->getPost('period_end');
        $run   = $this->runs->latestCompletedForPeriod($start, $end);
        $back  = route_to('helpdesk.agent', $glpiUserId) . '?period_start=' . $start . '&period_end=' . $end;

        if ($run === null) {
            return redirect()->to($back)->with('error', 'No hay auditoría para el período.');
        }

        $ids = (array) ($this->request->getPost('confirmed') ?? []);
        $uid = session()->get('user_id');
        $n   = $this->deviations->setConfirmedForAgentRun((int) $run['id'], $glpiUserId, $ids, $uid !== null ? (int) $uid : null);

        return redirect()->to($back)->with('success', "Se marcaron {$n} desviación(es) como procedentes (visibles para el agente).");
    }

    public function rule(string $ruleKey): string
    {
        [$start, $end] = $this->period();
        $run = $this->runs->latestCompletedForPeriod($start, $end);

        $deviations = $run ? $this->deviations->forRule((int) $run['id'], $ruleKey) : [];
        $meta       = (new RuleRegistry())->catalog()[$ruleKey] ?? ['name' => $ruleKey, 'manual' => '', 'kpi' => null, 'severity' => 'warning'];

        return view('App\Modules\HelpdeskSupervisor\Views\rule_detail', [
            'pageTitle'   => $meta['name'],
            'ruleKey'     => $ruleKey,
            'meta'        => $meta,
            'run'         => $run,
            'periodStart' => $start,
            'periodEnd'   => $end,
            'deviations'  => $deviations,
            'glpiBaseUrl' => $this->glpiBaseUrl(),
        ]);
    }

    /** Selected period from GET, defaulting to the current calendar month. */
    private function period(): array
    {
        $start = (string) $this->request->getGet('period_start');
        $end   = (string) $this->request->getGet('period_end');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = date('Y-m-t');
        }
        return [$start, $end];
    }

    /** GLPI base URL for building ticket links (from Provisioning settings, else the manual host). */
    private function glpiBaseUrl(): string
    {
        try {
            $s = service('provisioningSettings')->getAll();
            $url = trim((string) ($s['glpi_url'] ?? $s['glpi_base_url'] ?? ''));
            if ($url !== '') {
                return rtrim($url, '/');
            }
        } catch (\Throwable) {
            // fall through to default
        }
        return 'https://helpdesk.trantortechnologies.mx';
    }
}
