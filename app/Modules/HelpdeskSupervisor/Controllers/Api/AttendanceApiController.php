<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\HelpdeskSupervisor\Services\PeriodFilter;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * JSON mirror of the attendance supervisor screen (web: Attendance). Every
 * action is restricted to the configured attendance supervisor / SuperAdmin,
 * enforced the same way as the web controller.
 */
class AttendanceApiController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        if ($denied = $this->denyIfNotSupervisor()) {
            return $denied;
        }

        $attendance = service('serviceDeskAttendance');
        [$start, $end] = PeriodFilter::resolveFromRequest($this->request);

        return $this->success([
            'period_start' => $start,
            'period_end'   => $end,
            'pending'      => $attendance->pendingPermits(),
            'incidences'   => $attendance->incidencesSummary($start, $end),
            'history'      => $attendance->historyRange($start, $end),
        ]);
    }

    public function approvePermit(int $id): ResponseInterface
    {
        if ($denied = $this->denyIfNotSupervisor()) {
            return $denied;
        }

        $body   = $this->request->getJSON(true) ?? [];
        $result = service('serviceDeskAttendance')->approvePermit($id, (int) session()->get('user_id'), (string) ($body['review_notes'] ?? '') ?: null);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }
        return $this->success(['message' => $result->message]);
    }

    public function rejectPermit(int $id): ResponseInterface
    {
        if ($denied = $this->denyIfNotSupervisor()) {
            return $denied;
        }

        $body   = $this->request->getJSON(true) ?? [];
        $result = service('serviceDeskAttendance')->rejectPermit($id, (int) session()->get('user_id'), (string) ($body['review_notes'] ?? '') ?: null);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }
        return $this->success(['message' => $result->message]);
    }

    private function denyIfNotSupervisor(): ?ResponseInterface
    {
        $userId = (int) session()->get('user_id');
        if (! service('serviceDeskAttendance')->isSupervisor($userId)) {
            return $this->error('No autorizado.', ResponseInterface::HTTP_FORBIDDEN);
        }
        return null;
    }
}
