<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Services\AttendanceExportService;
use App\Modules\HelpdeskSupervisor\Services\PeriodFilter;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Mirror of ServiceDesk's attendance for the single attendance supervisor
 * (and any SuperAdmin): pending permits to approve, full history, an
 * incidence summary (retardos/ausencias) and an Excel export.
 *
 * Goes through App\Modules\ServiceDesk\Services\AttendanceService exclusively
 * — never touches servicedesk_attendance_* directly, same cross-module rule
 * as HelpdeskSupervisorBridge.
 */
class Attendance extends BaseController
{
    public function index(): string
    {
        $this->denyIfNotSupervisor();

        $attendance = service('serviceDeskAttendance');
        [$start, $end] = PeriodFilter::resolveFromRequest($this->request);

        return view('App\Modules\HelpdeskSupervisor\Views\attendance\index', [
            'pageTitle'  => 'Asistencia',
            'periodStart' => $start,
            'periodEnd'   => $end,
            'monthLabels' => PeriodFilter::monthLabels(),
            'pending'    => $attendance->pendingPermits(),
            'incidences' => $attendance->incidencesSummary($start, $end),
            'history'    => $attendance->historyRange($start, $end),
        ]);
    }

    public function approvePermit(int $id): RedirectResponse
    {
        $this->denyIfNotSupervisor();

        $notes  = trim((string) $this->request->getPost('review_notes'));
        $result = service('serviceDeskAttendance')->approvePermit($id, (int) session()->get('user_id'), $notes !== '' ? $notes : null);

        return redirect()->to(route_to('helpdesk.attendance.index'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function rejectPermit(int $id): RedirectResponse
    {
        $this->denyIfNotSupervisor();

        $notes  = trim((string) $this->request->getPost('review_notes'));
        $result = service('serviceDeskAttendance')->rejectPermit($id, (int) session()->get('user_id'), $notes !== '' ? $notes : null);

        return redirect()->to(route_to('helpdesk.attendance.index'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function export(): ResponseInterface|RedirectResponse
    {
        $this->denyIfNotSupervisor();

        [$start, $end] = PeriodFilter::resolveFromRequest($this->request);
        $logs = service('serviceDeskAttendance')->historyRange($start, $end);

        $filename = 'asistencia_' . $start . '_' . $end;

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"')
            ->setBody((new AttendanceExportService())->toXlsx($logs));
    }

    /** Only the configured attendance supervisor (or SuperAdmin) may see this. */
    private function denyIfNotSupervisor(): void
    {
        $userId = (int) session()->get('user_id');
        if (! service('serviceDeskAttendance')->isSupervisor($userId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}
