<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Agent-facing attendance: check in/out for the day and see the whole team's
 * week so far (transparency lets a covering agent see who has not marked in).
 */
class Attendance extends BaseController
{
    public function index(): string
    {
        $service = service('serviceDeskAttendance');
        $userId  = (int) session()->get('user_id');

        return view('App\Modules\ServiceDesk\Views\attendance', [
            'pageTitle' => 'Asistencia',
            'today'     => $service->todayForUser($userId),
            'board'     => $service->weeklyBoard(),
            // Full label + one-line description, for the check-in picker and the
            // "mi jornada" status line (plenty of room there).
            'schemes'   => [
                'presencial'  => 'Presencial',
                'home_office' => 'Home office',
                'permiso'     => 'Home office · con permiso',
            ],
            'schemeDescriptions' => [
                'presencial'  => 'Trabajas desde la oficina hoy.',
                'home_office' => 'Trabajas desde casa, como tenías programado.',
                'permiso'     => 'Casa hoy aunque te tocaba oficina. Tu supervisor lo revisa después.',
            ],
            // Compact label for the crowded weekly grid.
            'schemesShort' => [
                'presencial'  => 'Presencial',
                'home_office' => 'Home office',
                'permiso'     => 'Con permiso',
            ],
            'expectedCheckin' => $service->expectedCheckin(),
            'myUserId'  => $userId,
        ]);
    }

    public function checkIn(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $scheme = (string) $this->request->getPost('scheme');
        $notes  = trim((string) $this->request->getPost('notes'));

        $result = service('serviceDeskAttendance')->checkIn($userId, $scheme, $notes !== '' ? $notes : null);

        return redirect()->to(route_to('servicedesk.attendance'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function checkOut(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $result = service('serviceDeskAttendance')->checkOut($userId);

        return redirect()->to(route_to('servicedesk.attendance'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }
}
