<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\AgentRunStatsModel;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Models\EscalationModel;
use App\Modules\HelpdeskSupervisor\Rules\RuleRegistry;
use App\Modules\HelpdeskSupervisor\Services\DeviationExportService;
use App\Modules\HelpdeskSupervisor\Services\PeriodFilter;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Supervisor dashboard: period selector, global metrics, agent ranking and the
 * top offending rules for the latest completed run of the selected period.
 */
class Dashboard extends BaseController
{
    private const RULE_PER_PAGE    = 50;
    private const RULE_EXPORT_MAX  = 50000;
    private const AGENT_PER_PAGE   = 50;

    private AuditRunModel $runs;
    private DeviationModel $deviations;
    private AgentRunStatsModel $agentStats;

    public function __construct()
    {
        $this->runs       = new AuditRunModel();
        $this->deviations = new DeviationModel();
        $this->agentStats = new AgentRunStatsModel();
    }

    public function index(): string
    {
        [$start, $end] = $this->period();
        $run = $this->runs->latestCompletedForPeriod($start, $end);

        $agents = $run ? $this->agentsForRun((int) $run['id']) : [];
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
            'pageTitle'       => 'Dashboard',
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
        $page          = max(1, (int) $this->request->getGet('page'));
        $perPage       = max(1, min(200, (int) ($this->request->getGet('per_page') ?: self::AGENT_PER_PAGE)));
        $total         = 0;
        $lastPage      = 1;
        $deviations    = [];
        $agentName     = '';
        $ruleTotals    = [];
        $agentStat     = null;
        $escalations   = [];

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
                'ruleTotals'  => [],
                'agentStat'   => null,
                'totalAll'    => 0,
                'total'       => 0,
                'ruleFilter'  => null,
                'page'        => 1,
                'perPage'     => $perPage,
                'lastPage'    => 1,
            ]);
        }

        $runId = (int) $run['id'];
        foreach ($this->deviations->ruleSummaryForAgent($runId, $glpiUserId) as $r) {
            $key = (string) $r['rule_key'];
            $ruleTotals[$key]['rule_name'] = (string) $r['rule_name'];
            $ruleTotals[$key]['severity']  = (string) $r['severity'];
            $ruleTotals[$key]['count']     = ($ruleTotals[$key]['count'] ?? 0) + (int) $r['count'];
        }
        uasort($ruleTotals, static fn(array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        $ruleFilter = $this->agentRuleFilter();
        if ($ruleFilter !== null && ! isset($ruleTotals[$ruleFilter])) {
            $ruleFilter = null;
        }

        $totalAll = $this->deviations->countForAgent($runId, $glpiUserId);
        $total    = $ruleFilter !== null
            ? $this->deviations->countForAgent($runId, $glpiUserId, $ruleFilter)
            : $totalAll;

        if ($total > 0) {
            $lastPage   = max(1, (int) ceil($total / $perPage));
            $page       = min($page, $lastPage);
            $deviations = $this->deviations->forAgentPaginated($runId, $glpiUserId, $page, $perPage, $ruleFilter);
            $agentName  = (string) ($deviations[0]['agent_name'] ?? '');
        }

        if ($agentName === '') {
            $stat = $this->agentStats->forRunAgent($runId, $glpiUserId);
            $agentName = (string) ($stat['agent_name'] ?? '');
        }

        $agentStat = $this->agentStats->forRunAgent($runId, $glpiUserId);
        $devSummary = [];
        foreach ($this->deviations->agentSummary($runId) as $row) {
            if ((int) $row['glpi_user_id'] === $glpiUserId) {
                $devSummary = $row;
                break;
            }
        }

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
            'ruleTotals'  => $ruleTotals,
            'agentStat'   => $agentStat,
            'devSummary'  => $devSummary,
            'totalAll'    => $totalAll,
            'total'       => $total,
            'ruleFilter'  => $ruleFilter,
            'page'        => $page,
            'perPage'     => $perPage,
            'lastPage'    => $lastPage,
            'glpiBaseUrl' => $this->glpiBaseUrl(),
        ]);
    }

    /** Marks which of an agent's deviations "proceden" (visible to the agent). */
    public function confirmAgent(int $glpiUserId): \CodeIgniter\HTTP\RedirectResponse
    {
        $start = (string) $this->request->getPost('period_start');
        $end   = (string) $this->request->getPost('period_end');
        $page  = max(1, (int) $this->request->getPost('page'));
        $rule  = trim((string) $this->request->getPost('rule'));
        $run   = $this->runs->latestCompletedForPeriod($start, $end);
        $backQ = [
            'period_start' => $start,
            'period_end'   => $end,
        ];
        if ($page > 1) {
            $backQ['page'] = (string) $page;
        }
        if ($rule !== '' && preg_match('/^[a-z_]+$/', $rule)) {
            $backQ['rule'] = $rule;
        }
        $back = route_to('helpdesk.agent', $glpiUserId) . '?' . http_build_query($backQ);

        if ($run === null) {
            return redirect()->to($back)->with('error', 'No hay auditoría para el período.');
        }

        $ids     = (array) ($this->request->getPost('confirmed') ?? []);
        $pageIds = (array) ($this->request->getPost('page_ids') ?? []);
        $uid     = session()->get('user_id');
        $n       = $this->deviations->setConfirmedForAgentRunPage(
            (int) $run['id'],
            $glpiUserId,
            $pageIds,
            $ids,
            $uid !== null ? (int) $uid : null,
        );

        return redirect()->to($back)->with('success', "Se marcaron {$n} desviación(es) como procedentes (visibles para el agente).");
    }

    public function rule(string $ruleKey): string
    {
        [$start, $end] = $this->period();
        $run           = $this->runs->latestCompletedForPeriod($start, $end);
        $meta          = $this->ruleMeta($ruleKey);
        $page          = max(1, (int) $this->request->getGet('page'));
        $perPage       = max(1, min(200, (int) ($this->request->getGet('per_page') ?: self::RULE_PER_PAGE)));
        $total         = 0;
        $lastPage      = 1;
        $deviations    = [];

        if ($run !== null) {
            $runId    = (int) $run['id'];
            $total    = $this->deviations->countForRule($runId, $ruleKey);
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page     = min($page, $lastPage);
            $deviations = $this->deviations->forRulePaginated($runId, $ruleKey, $page, $perPage);
        }

        return view('App\Modules\HelpdeskSupervisor\Views\rule_detail', [
            'pageTitle'   => $meta['name'],
            'ruleKey'     => $ruleKey,
            'meta'        => $meta,
            'run'         => $run,
            'periodStart' => $start,
            'periodEnd'   => $end,
            'deviations'  => $deviations,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'lastPage'    => $lastPage,
            'glpiBaseUrl' => $this->glpiBaseUrl(),
        ]);
    }

    public function ruleExport(string $ruleKey): ResponseInterface|RedirectResponse
    {
        [$start, $end] = $this->period();
        $run           = $this->runs->latestCompletedForPeriod($start, $end);
        $meta          = $this->ruleMeta($ruleKey);
        $format        = strtolower((string) $this->request->getGet('format'));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $back = route_to('helpdesk.rule', $ruleKey)
            . '?period_start=' . rawurlencode($start)
            . '&period_end=' . rawurlencode($end);

        if ($run === null) {
            return redirect()->to($back)->with('error', 'No hay auditoría para el período.');
        }

        $runId = (int) $run['id'];
        $total = $this->deviations->countForRule($runId, $ruleKey);
        if ($total > self::RULE_EXPORT_MAX) {
            return redirect()->to($back)->with(
                'error',
                'Hay más de ' . number_format(self::RULE_EXPORT_MAX) . ' incumplimientos. Acota el período antes de exportar.',
            );
        }

        $rows     = $this->deviations->forRuleExport($runId, $ruleKey, self::RULE_EXPORT_MAX);
        $portal   = $this->glpiBaseUrl();
        $exporter = new DeviationExportService();
        $slug     = preg_replace('/[^A-Za-z0-9]+/', '_', $meta['name']) ?: $ruleKey;
        $filename = 'desviaciones_' . trim($slug, '_') . '_' . date('Y-m-d_His');

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

    public function agentExport(int $glpiUserId): ResponseInterface|RedirectResponse
    {
        [$start, $end] = $this->period();
        $run           = $this->runs->latestCompletedForPeriod($start, $end);
        $format        = strtolower((string) $this->request->getGet('format'));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $ruleFilter = $this->agentRuleFilter();
        $backQ      = [
            'period_start' => $start,
            'period_end'   => $end,
        ];
        if ($ruleFilter !== null) {
            $backQ['rule'] = $ruleFilter;
        }
        $back = route_to('helpdesk.agent', $glpiUserId) . '?' . http_build_query($backQ);

        if ($run === null) {
            return redirect()->to($back)->with('error', 'No hay auditoría para el período.');
        }

        $ruleFilter = $this->agentRuleFilter();
        $runId      = (int) $run['id'];
        $total      = $this->deviations->countForAgent($runId, $glpiUserId, $ruleFilter);
        if ($total > self::RULE_EXPORT_MAX) {
            return redirect()->to($back)->with(
                'error',
                'Hay más de ' . number_format(self::RULE_EXPORT_MAX) . ' desviaciones. Acota el período antes de exportar.',
            );
        }

        $rows     = $this->deviations->forAgentExport($runId, $glpiUserId, self::RULE_EXPORT_MAX, $ruleFilter);
        $portal   = $this->glpiBaseUrl();
        $exporter = new DeviationExportService();
        $slug     = preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($rows[0]['agent_name'] ?? 'agente_' . $glpiUserId)) ?: ('agente_' . $glpiUserId);
        if ($ruleFilter !== null) {
            $slug .= '_' . $ruleFilter;
        }
        $filename = 'desviaciones_' . trim($slug, '_') . '_' . date('Y-m-d_His');

        if ($format === 'xlsx') {
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"')
                ->setBody($exporter->toXlsx($rows, $portal, true));
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $exporter->toCsv($rows, $portal, true));
    }

    /** @return array{name:string,manual:string,kpi:?string,severity:string} */
    private function ruleMeta(string $ruleKey): array
    {
        return (new RuleRegistry())->catalog()[$ruleKey]
            ?? ['name' => $ruleKey, 'manual' => '', 'kpi' => null, 'severity' => 'warning'];
    }

    /** Selected period from GET: month/year shortcut or custom dates; default = current month. */
    private function period(): array
    {
        return PeriodFilter::resolveFromRequest($this->request);
    }

    /** Optional rule_key filter for agent deviation drill-down. */
    private function agentRuleFilter(): ?string
    {
        $rule = trim((string) $this->request->getGet('rule'));
        if ($rule === '' || ! preg_match('/^[a-z_]+$/', $rule)) {
            return null;
        }

        return $rule;
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

    /**
     * Agent ranking for a run: denominators from agent_run_stats (same as Agent
     * KPIs), deviation counts merged from the deviations table.
     *
     * @return array<int,array<string,mixed>>
     */
    private function agentsForRun(int $auditRunId): array
    {
        $devByGlpi = [];
        foreach ($this->deviations->agentSummary($auditRunId) as $row) {
            $devByGlpi[(int) $row['glpi_user_id']] = $row;
        }

        $agents = [];
        foreach ($this->agentStats->forRun($auditRunId) as $stat) {
            $glpiId = (int) $stat['glpi_user_id'];
            $dev    = $devByGlpi[$glpiId] ?? [];
            $agents[] = [
                'glpi_user_id'            => $glpiId,
                'nexus_user_id'           => $stat['nexus_user_id'] ?? null,
                'agent_name'              => (string) ($stat['agent_name'] ?? $dev['agent_name'] ?? ''),
                'total_tickets'           => (int) ($stat['total_tickets'] ?? 0),
                'tickets_with_deviations' => (int) ($dev['tickets_with_deviations'] ?? 0),
                'deviations'              => (int) ($dev['deviations'] ?? 0),
                'criticals'               => (int) ($dev['criticals'] ?? 0),
                'warnings'                => (int) ($dev['warnings'] ?? 0),
                'infos'                   => (int) ($dev['infos'] ?? 0),
            ];
        }

        usort($agents, static function (array $a, array $b): int {
            $byDev = ($b['deviations'] <=> $a['deviations']);
            if ($byDev !== 0) {
                return $byDev;
            }

            return ($b['total_tickets'] <=> $a['total_tickets']);
        });

        return $agents;
    }
}
