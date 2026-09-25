<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Services\PeriodFilter;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CSAT survey results, read exclusively through MailDispatch's SurveyService —
 * never touches maildispatch_survey_* directly, same cross-module rule as
 * HelpdeskSupervisorBridge and Attendance.
 */
class Satisfaction extends BaseController
{
    public function index(): string
    {
        [$start, $end] = PeriodFilter::resolveFromRequest($this->request);
        $filters = $this->filters($start, $end);

        $survey = service('mailDispatchSurvey');
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPage = 25;
        $result  = $survey->list($filters, $page, $perPage);

        return view('App\Modules\HelpdeskSupervisor\Views\satisfaction', [
            'pageTitle'   => 'Satisfacción',
            'periodStart' => $start,
            'periodEnd'   => $end,
            'monthLabels' => PeriodFilter::monthLabels(),
            'filters'     => $filters,
            'stats'       => $survey->stats($filters),
            'agents'      => $survey->agentsInRange(['from' => $start, 'to' => $end]),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'lastPage'    => max(1, (int) ceil($result['total'] / $perPage)),
            // The thread link only renders if this supervisor also has access
            // to MailDispatch — they may not, and it would 403 otherwise.
            'canOpenThread' => service('access')->canAccessModule('mail_dispatch'),
        ]);
    }

    public function export(): ResponseInterface
    {
        [$start, $end] = PeriodFilter::resolveFromRequest($this->request);
        $csv = service('mailDispatchSurvey')->csv($this->filters($start, $end));

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="satisfaccion-' . date('Ymd-His') . '.csv"')
            ->setBody($csv);
    }

    /** @return array{from:string,to:string,agent_id?:int,rating?:int,resolved?:string,q?:string} */
    private function filters(string $start, string $end): array
    {
        $filters = ['from' => $start, 'to' => $end];

        $agentId = (int) $this->request->getGet('agent_id');
        if ($agentId > 0) {
            $filters['agent_id'] = $agentId;
        }
        $rating = (int) $this->request->getGet('rating');
        if ($rating >= 1 && $rating <= 5) {
            $filters['rating'] = $rating;
        }
        $resolved = (string) $this->request->getGet('resolved');
        if (in_array($resolved, ['yes', 'partial', 'no'], true)) {
            $filters['resolved'] = $resolved;
        }
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $filters['q'] = $q;
        }

        return $filters;
    }
}
