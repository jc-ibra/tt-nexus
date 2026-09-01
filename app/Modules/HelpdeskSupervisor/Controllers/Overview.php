<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Services\OverviewTicketExportService;
use App\Modules\HelpdeskSupervisor\Services\PeriodFilter;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Live GLPI overview: backlog now + period report + ticket drill-down.
 */
class Overview extends BaseController
{
    private const PER_PAGE = 50;

    public function index(): string
    {
        $mode  = $this->request->getGet('mode') === 'period' ? 'period' : 'backlog';
        $force = $this->request->getGet('refresh') === '1';
        [$start, $end] = $this->period();

        $overview = service('helpdeskGlpiOverview');
        $result   = $overview->build(
            $force,
            $mode,
            $mode === 'period' ? $start : null,
            $mode === 'period' ? $end : null,
        );

        return view('App\Modules\HelpdeskSupervisor\Views\overview', [
            'pageTitle'   => 'Resumen GLPI',
            'ok'          => $result->success,
            'message'     => $result->message,
            'data'        => $result->success ? ($result->data ?? []) : [],
            'mode'        => $mode,
            'periodStart' => $start,
            'periodEnd'   => $end,
            'glpiBaseUrl' => $overview->glpiPortalUrl(),
        ]);
    }

    public function tickets(): string
    {
        $ctx      = $this->drillContext();
        $page     = max(1, (int) $this->request->getGet('page'));
        $perPage  = max(1, min(200, (int) ($this->request->getGet('per_page') ?: self::PER_PAGE)));
        $overview = service('helpdeskGlpiOverview');
        $result   = $overview->listTickets(
            $ctx['dimension'],
            $ctx['filterId'],
            $ctx['mode'],
            $ctx['mode'] === 'period' ? $ctx['periodStart'] : null,
            $ctx['mode'] === 'period' ? $ctx['periodEnd'] : null,
            $page,
            $perPage,
        );

        $dimLabels = [
            'category'   => 'Categoría',
            'source'     => 'Fuente de solicitud',
            'requester'  => 'Solicitante',
            'assignee'   => 'Asignado',
            'status'     => 'Estatus',
            'type'       => 'Tipo',
            'backlog'    => 'Backlog abierto',
            'still_open' => 'Aún abiertos',
            'critical'   => 'Críticos',
            'period'     => 'Tickets del período',
        ];

        $data = is_array($result->data) ? $result->data : [];

        return view('App\Modules\HelpdeskSupervisor\Views\overview_tickets', [
            'pageTitle'   => 'Tickets · Resumen GLPI',
            'ok'          => $result->success,
            'message'     => $result->message,
            'tickets'     => $data['tickets'] ?? [],
            'total'       => (int) ($data['total'] ?? 0),
            'page'        => (int) ($data['page'] ?? 1),
            'perPage'     => (int) ($data['per_page'] ?? $perPage),
            'lastPage'    => (int) ($data['total_pages'] ?? 1),
            'dimension'   => $ctx['dimension'],
            'dimLabel'    => $dimLabels[$ctx['dimension']] ?? 'Filtro',
            'filterLabel' => $ctx['label'],
            'filterId'    => $ctx['filterId'],
            'mode'        => $ctx['mode'],
            'periodStart' => $ctx['periodStart'],
            'periodEnd'   => $ctx['periodEnd'],
            'glpiBaseUrl' => $overview->glpiPortalUrl(),
        ]);
    }

    public function ticketsExport(): ResponseInterface|RedirectResponse
    {
        $ctx      = $this->drillContext();
        $format   = strtolower((string) $this->request->getGet('format'));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $overview = service('helpdeskGlpiOverview');
        $result   = $overview->listTicketsForExport(
            $ctx['dimension'],
            $ctx['filterId'],
            $ctx['mode'],
            $ctx['mode'] === 'period' ? $ctx['periodStart'] : null,
            $ctx['mode'] === 'period' ? $ctx['periodEnd'] : null,
        );

        if (! $result->success) {
            return redirect()->to($this->ticketsListUrl($ctx))
                ->with('error', $result->message);
        }

        $tickets  = is_array($result->data) ? ($result->data['tickets'] ?? []) : [];
        $portal   = $overview->glpiPortalUrl();
        $exporter = new OverviewTicketExportService();
        $slug     = preg_replace('/[^A-Za-z0-9]+/', '_', $ctx['label']) ?: 'tickets';
        $filename = 'tickets_' . trim($slug, '_') . '_' . date('Y-m-d_His');

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

    public function refresh(): RedirectResponse
    {
        service('helpdeskGlpiOverview')->invalidateCache();
        $mode  = $this->request->getPost('mode') === 'period' ? 'period' : 'backlog';
        $start = (string) $this->request->getPost('period_start');
        $end   = (string) $this->request->getPost('period_end');
        $q     = ['refresh' => '1', 'mode' => $mode];
        if ($mode === 'period') {
            $q['period_start'] = $start;
            $q['period_end']   = $end;
        }
        return redirect()->to(route_to('helpdesk.overview') . '?' . http_build_query($q));
    }

    /**
     * @return array{
     *   dimension:string,
     *   filterId:int,
     *   label:string,
     *   mode:string,
     *   periodStart:string,
     *   periodEnd:string
     * }
     */
    private function drillContext(): array
    {
        [$start, $end] = $this->period();
        $dimension = (string) $this->request->getGet('dimension');
        $allowed   = ['category', 'source', 'requester', 'assignee', 'status', 'type', 'backlog', 'still_open', 'critical', 'period'];
        if (! in_array($dimension, $allowed, true)) {
            $dimension = 'category';
        }

        return [
            'dimension'   => $dimension,
            'filterId'    => (int) $this->request->getGet('id'),
            'label'       => trim((string) $this->request->getGet('label')),
            'mode'        => $this->request->getGet('mode') === 'period' ? 'period' : 'backlog',
            'periodStart' => $start,
            'periodEnd'   => $end,
        ];
    }

    /**
     * @param array{dimension:string,filterId:int,label:string,mode:string,periodStart:string,periodEnd:string} $ctx
     */
    private function ticketsListUrl(array $ctx, int $page = 1, int $perPage = self::PER_PAGE): string
    {
        $q = [
            'dimension' => $ctx['dimension'],
            'id'        => $ctx['filterId'],
            'mode'      => $ctx['mode'],
            'label'     => $ctx['label'],
            'page'      => $page,
            'per_page'  => $perPage,
        ];
        if ($ctx['mode'] === 'period') {
            $q['period_start'] = $ctx['periodStart'];
            $q['period_end']   = $ctx['periodEnd'];
        }
        return route_to('helpdesk.overview.tickets') . '?' . http_build_query($q);
    }

    /** @return array{0:string,1:string} */
    private function period(): array
    {
        return PeriodFilter::resolveFromRequest($this->request);
    }
}
