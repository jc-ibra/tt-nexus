<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API v1 mirror of the agent-facing attendance screen (web: Attendance).
 * Check-in/out always resolve to the token's own user.
 */
class AttendanceApiController extends BaseApiController
{
    /** GET /api/v1/servicedesk/attendance */
    public function index(): ResponseInterface
    {
        $service = service('serviceDeskAttendance');
        $userId  = $this->userId();

        return $this->success([
            'today'             => $service->todayForUser($userId),
            'board'             => $service->weeklyBoard(),
            'expected_checkin'  => $service->expectedCheckin(),
        ]);
    }

    /** POST /api/v1/servicedesk/attendance/checkin */
    public function checkIn(): ResponseInterface
    {
        $body   = $this->request->getJSON(true) ?? [];
        $scheme = (string) ($body['scheme'] ?? $this->request->getPost('scheme'));
        $notes  = trim((string) ($body['notes'] ?? $this->request->getPost('notes') ?? ''));

        $result = service('serviceDeskAttendance')->checkIn($this->userId(), $scheme, $notes !== '' ? $notes : null);
        if (! $result->success) {
            return $this->error($result->message, 422);
        }
        return $this->success(['message' => $result->message]);
    }

    /** POST /api/v1/servicedesk/attendance/checkout */
    public function checkOut(): ResponseInterface
    {
        $result = service('serviceDeskAttendance')->checkOut($this->userId());
        if (! $result->success) {
            return $this->error($result->message, 422);
        }
        return $this->success(['message' => $result->message]);
    }

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }
}
