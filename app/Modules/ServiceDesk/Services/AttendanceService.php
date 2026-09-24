<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\ServiceDesk\Models\AttendanceLogModel;
use App\Modules\ServiceDesk\Models\AttendancePermitModel;
use App\Modules\ServiceDesk\Models\ServiceDeskSettingsModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Attendance / jornada laboral: check-in/out, the weekly team board, and the
 * supervisor's approvals + history + incidence summary.
 *
 * This is the ONLY entry point HelpdeskSupervisor's attendance mirror is
 * allowed to use — it never touches servicedesk_attendance_* directly, the
 * same cross-module rule the rest of the platform follows (see
 * HelpdeskSupervisorBridge).
 */
class AttendanceService
{
    public const SCHEMES = ['presencial', 'home_office', 'permiso'];

    private BaseConnection $db;

    public function __construct(
        private AttendanceLogModel $logs,
        private AttendancePermitModel $permits,
        private ServiceDeskSettingsModel $settings,
    ) {
        $this->db = \Config\Database::connect();
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public function enabled(): bool
    {
        return $this->settings->get('attendance_enabled', '1') === '1';
    }

    public function expectedCheckin(): string
    {
        $raw = trim($this->settings->get('attendance_expected_checkin', '09:00'));
        return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw) ? $raw : '09:00';
    }

    public function toleranceMinutes(): int
    {
        return max(0, (int) $this->settings->get('attendance_tolerance_minutes', '15'));
    }

    public function supervisorUserId(): int
    {
        return max(0, (int) $this->settings->get('attendance_supervisor_user_id', '0'));
    }

    public function saveSettings(array $input): ServiceResult
    {
        $checkin = trim((string) ($input['attendance_expected_checkin'] ?? '09:00'));
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $checkin)) {
            $checkin = '09:00';
        }

        $this->settings->setMany([
            'attendance_enabled'            => ! empty($input['attendance_enabled']) ? '1' : '0',
            'attendance_expected_checkin'   => $checkin,
            'attendance_tolerance_minutes'  => (string) max(0, (int) ($input['attendance_tolerance_minutes'] ?? 15)),
            'attendance_supervisor_user_id' => (string) max(0, (int) ($input['attendance_supervisor_user_id'] ?? 0)),
        ]);

        return ServiceResult::ok(null, 'Configuración de asistencia guardada.');
    }

    /** True for the configured supervisor or any SuperAdmin. */
    public function isSupervisor(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (service('access')->isSuperAdmin()) {
            return true;
        }
        return $this->supervisorUserId() > 0 && $this->supervisorUserId() === $userId;
    }

    // ------------------------------------------------------------------
    // Check-in / check-out
    // ------------------------------------------------------------------

    public function checkIn(int $userId, string $scheme, ?string $notes = null): ServiceResult
    {
        if (! in_array($scheme, self::SCHEMES, true)) {
            return ServiceResult::fail('Esquema inválido.');
        }

        $today   = date('Y-m-d');
        $existing = $this->logs->todayForUser($userId, $today);
        if ($existing !== null && $existing['check_in_at'] !== null) {
            return ServiceResult::fail('Ya iniciaste tu jornada hoy.');
        }

        $permitId = null;
        if ($scheme === 'permiso') {
            $permitId = $this->permits->insert([
                'user_id'    => $userId,
                'work_date'  => $today,
                'reason'     => $notes !== null ? mb_substr(trim($notes), 0, 500) : null,
                'status'     => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ], true);
        }

        $data = [
            'user_id'     => $userId,
            'work_date'   => $today,
            'scheme'      => $scheme,
            'check_in_at' => date('Y-m-d H:i:s'),
            'permit_id'   => $permitId,
        ];

        if ($existing !== null) {
            $this->logs->update($existing['id'], $data);
        } else {
            $this->logs->insert($data);
        }

        return ServiceResult::ok(null, 'Jornada iniciada.');
    }

    public function checkOut(int $userId): ServiceResult
    {
        $today = date('Y-m-d');
        $log   = $this->logs->todayForUser($userId, $today);

        if ($log === null || $log['check_in_at'] === null) {
            return ServiceResult::fail('Primero debes iniciar tu jornada.');
        }
        if ($log['check_out_at'] !== null) {
            return ServiceResult::fail('Ya cerraste tu jornada hoy.');
        }

        $this->logs->update($log['id'], ['check_out_at' => date('Y-m-d H:i:s')]);

        return ServiceResult::ok(null, 'Jornada cerrada.');
    }

    public function todayForUser(int $userId): ?array
    {
        return $this->logs->todayForUser($userId, date('Y-m-d'));
    }

    // ------------------------------------------------------------------
    // Roster (active users with ServiceDesk module access)
    // ------------------------------------------------------------------

    /** @return array<int,array{id:int,name:string}> */
    public function roster(): array
    {
        $rows = $this->db->table('core_users u')
            ->distinct()
            ->select('u.id, u.name')
            ->join('core_user_roles ur', 'ur.user_id = u.id')
            ->join('core_role_modules rm', 'rm.role_id = ur.role_id')
            ->join('core_modules m', 'm.id = rm.module_id')
            ->where('m.key', 'servicedesk')
            ->where('u.status', 'active')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $rows);
    }

    // ------------------------------------------------------------------
    // Weekly transparent board (whole team, ServiceDesk side)
    // ------------------------------------------------------------------

    /**
     * Monday-to-Sunday board for the week containing $anchorDate (default
     * today): every roster agent x every day, merged with whatever log/permit
     * exists. Used both by the team's read-only week view and the supervisor's
     * dashboard.
     *
     * @return array{start:string,end:string,days:string[],rows:array<int,array{user_id:int,name:string,cells:array<string,array<string,mixed>>}>}
     */
    public function weeklyBoard(?string $anchorDate = null): array
    {
        $anchor = $anchorDate ?? date('Y-m-d');
        $ts     = strtotime($anchor) ?: time();
        $monday = date('Y-m-d', strtotime('monday this week', $ts));
        $sunday = date('Y-m-d', strtotime('sunday this week', $ts));

        $days = [];
        for ($d = $monday; $d <= $sunday; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $days[] = $d;
        }

        $roster = $this->roster();
        $logs   = $this->logs->rangeWithUsers($monday, $sunday);

        $byUserDay = [];
        foreach ($logs as $l) {
            $byUserDay[(int) $l['user_id']][(string) $l['work_date']] = $l;
        }

        $today = date('Y-m-d');
        $rows  = [];
        foreach ($roster as $agent) {
            $cells = [];
            foreach ($days as $day) {
                $log = $byUserDay[$agent['id']][$day] ?? null;
                $cells[$day] = [
                    'has_log'      => $log !== null,
                    'scheme'       => $log['scheme'] ?? null,
                    'check_in_at'  => $log['check_in_at'] ?? null,
                    'check_out_at' => $log['check_out_at'] ?? null,
                    'permit_status' => $log['permit_status'] ?? null,
                    'is_absent'    => $log === null && $day < $today && $this->isWeekday($day),
                    'is_future'    => $day > $today,
                ];
            }
            $rows[] = ['user_id' => $agent['id'], 'name' => $agent['name'], 'cells' => $cells];
        }

        return ['start' => $monday, 'end' => $sunday, 'days' => $days, 'rows' => $rows];
    }

    // ------------------------------------------------------------------
    // Permits (supervisor)
    // ------------------------------------------------------------------

    public function pendingPermits(): array
    {
        return $this->permits->pendingWithUsers();
    }

    public function approvePermit(int $permitId, int $reviewerId, ?string $notes = null): ServiceResult
    {
        $permit = $this->permits->find($permitId);
        if ($permit === null) {
            return ServiceResult::fail('Permiso no encontrado.');
        }
        $this->permits->review($permitId, 'approved', $reviewerId, $notes);
        return ServiceResult::ok(null, 'Permiso aprobado.');
    }

    public function rejectPermit(int $permitId, int $reviewerId, ?string $notes = null): ServiceResult
    {
        $permit = $this->permits->find($permitId);
        if ($permit === null) {
            return ServiceResult::fail('Permiso no encontrado.');
        }
        $this->permits->review($permitId, 'rejected', $reviewerId, $notes);
        return ServiceResult::ok(null, 'Permiso rechazado.');
    }

    // ------------------------------------------------------------------
    // History + incidences (supervisor)
    // ------------------------------------------------------------------

    /** Full log history in a date range, with the requester's name. */
    public function historyRange(string $start, string $end): array
    {
        return $this->logs->rangeWithUsers($start, $end);
    }

    public function permitsRange(string $start, string $end): array
    {
        return $this->permits->rangeWithUsers($start, $end);
    }

    /**
     * Weekly incidence summary per agent: retardos (check-in later than the
     * expected time + tolerance) and ausencias (no log at all on a weekday
     * already past).
     *
     * @return array<int,array{user_id:int,name:string,late_count:int,absence_count:int}>
     */
    public function incidencesSummary(string $start, string $end): array
    {
        $roster = $this->roster();
        $logs   = $this->logs->rangeWithUsers($start, $end);

        $byUserDay = [];
        foreach ($logs as $l) {
            $byUserDay[(int) $l['user_id']][(string) $l['work_date']] = $l;
        }

        [$expH, $expM] = array_map('intval', explode(':', $this->expectedCheckin()));
        $toleranceSec  = $this->toleranceMinutes() * 60;
        $today         = date('Y-m-d');

        $out = [];
        foreach ($roster as $agent) {
            $late = 0;
            $absent = 0;
            for ($d = $start; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                if ($d > $today) {
                    continue;
                }
                $log = $byUserDay[$agent['id']][$d] ?? null;
                if ($log === null) {
                    if ($this->isWeekday($d)) {
                        $absent++;
                    }
                    continue;
                }
                if ($log['check_in_at'] === null) {
                    continue;
                }
                $checkInTs = strtotime($log['check_in_at']);
                $limitTs   = strtotime($d . sprintf(' %02d:%02d:00', $expH, $expM)) + $toleranceSec;
                if ($checkInTs > $limitTs) {
                    $late++;
                }
            }
            if ($late > 0 || $absent > 0) {
                $out[] = ['user_id' => $agent['id'], 'name' => $agent['name'], 'late_count' => $late, 'absence_count' => $absent];
            }
        }

        return $out;
    }

    private function isWeekday(string $date): bool
    {
        $dow = (int) date('N', strtotime($date));
        return $dow >= 1 && $dow <= 5;
    }
}
