<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\ServiceDesk\Models\AttendanceLogModel;
use App\Modules\ServiceDesk\Models\AttendancePermitModel;
use App\Modules\ServiceDesk\Models\AttendanceVacationModel;
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

    /** Excused period types the supervisor can register. */
    public const EXCUSED_TYPES = ['vacation', 'justified'];

    private BaseConnection $db;

    public function __construct(
        private AttendanceLogModel $logs,
        private AttendancePermitModel $permits,
        private ServiceDeskSettingsModel $settings,
        private AttendanceVacationModel $vacations,
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

    public function logForUserDate(int $userId, string $date): ?array
    {
        return $this->logs->todayForUser($userId, $date);
    }

    /**
     * Supervisor-only: register or complete a forgotten check-in/out for any
     * roster agent on any past (or current) date, with no look-back limit.
     * Upserts on (user_id, work_date); only the times sent are touched, so a
     * forgotten check-out can be added without altering the check-in.
     *
     * @param array{user_id?:mixed,work_date?:mixed,scheme?:mixed,check_in?:mixed,check_out?:mixed,reason?:mixed} $input
     */
    public function manualEntry(array $input, int $supervisorId): ServiceResult
    {
        if (! $this->isSupervisor($supervisorId)) {
            return ServiceResult::fail('No autorizado.');
        }

        $userId = (int) ($input['user_id'] ?? 0);
        if (! in_array($userId, array_column($this->roster(), 'id'), true)) {
            return ServiceResult::fail('El agente no pertenece al equipo.');
        }

        $date = trim((string) ($input['work_date'] ?? ''));
        $dt   = \DateTime::createFromFormat('Y-m-d', $date);
        if ($dt === false || $dt->format('Y-m-d') !== $date) {
            return ServiceResult::fail('Fecha inválida.');
        }
        $today = date('Y-m-d');
        if ($date > $today) {
            return ServiceResult::fail('No se pueden registrar fechas futuras.');
        }

        $scheme = (string) ($input['scheme'] ?? 'presencial');
        if (! in_array($scheme, ['presencial', 'home_office'], true)) {
            return ServiceResult::fail('Esquema inválido.');
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ServiceResult::fail('Indica el motivo del registro manual.');
        }
        $reason = mb_substr($reason, 0, 500);

        $times = [];
        foreach (['check_in' => 'entrada', 'check_out' => 'salida'] as $field => $label) {
            $raw = trim((string) ($input[$field] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw)) {
                return ServiceResult::fail("Hora de {$label} inválida.");
            }
            $stamp = $date . ' ' . sprintf('%02d:%02d:00', ...array_map('intval', explode(':', $raw)));
            if ($date === $today && strtotime($stamp) > time()) {
                return ServiceResult::fail("La hora de {$label} no puede ser futura.");
            }
            $times[$field] = $stamp;
        }

        if ($times === []) {
            return ServiceResult::fail('Captura al menos la hora de entrada o de salida.');
        }

        $existing = $this->logs->todayForUser($userId, $date);
        $checkIn  = $times['check_in'] ?? ($existing['check_in_at'] ?? null);
        $checkOut = $times['check_out'] ?? ($existing['check_out_at'] ?? null);

        if ($checkIn === null) {
            return ServiceResult::fail('Registra también la hora de entrada.');
        }
        if ($checkOut !== null && strtotime($checkOut) <= strtotime($checkIn)) {
            return ServiceResult::fail('La salida debe ser posterior a la entrada.');
        }

        $data = [
            'scheme'        => $scheme,
            'check_in_at'   => $checkIn,
            'check_out_at'  => $checkOut,
            'manual_by'     => $supervisorId,
            'manual_at'     => date('Y-m-d H:i:s'),
            'manual_reason' => $reason,
        ];

        if ($existing !== null) {
            $this->logs->update($existing['id'], $data);
        } else {
            $this->logs->insert($data + ['user_id' => $userId, 'work_date' => $date]);
        }

        return ServiceResult::ok(null, 'Registro guardado.');
    }

    // ------------------------------------------------------------------
    // Excused periods: vacations and justified absences (supervisor)
    // ------------------------------------------------------------------

    /** Periods overlapping the range, with the agent's name. */
    public function vacationsRange(string $start, string $end): array
    {
        return $this->vacations->overlapping($start, $end);
    }

    /**
     * Supervisor-only: register a vacation or justified-absence period
     * (inclusive, past or future, no limit). Weekdays in it are not absences.
     * Overlaps are rejected; a justified absence requires a reason.
     *
     * @param array{user_id?:mixed,type?:mixed,start_date?:mixed,end_date?:mixed,notes?:mixed} $input
     */
    public function addVacation(array $input, int $supervisorId): ServiceResult
    {
        if (! $this->isSupervisor($supervisorId)) {
            return ServiceResult::fail('No autorizado.');
        }

        $userId = (int) ($input['user_id'] ?? 0);
        if (! in_array($userId, array_column($this->roster(), 'id'), true)) {
            return ServiceResult::fail('El agente no pertenece al equipo.');
        }

        $type = (string) ($input['type'] ?? 'vacation');
        if (! in_array($type, self::EXCUSED_TYPES, true)) {
            return ServiceResult::fail('Tipo inválido.');
        }

        $start = trim((string) ($input['start_date'] ?? ''));
        $end   = trim((string) ($input['end_date'] ?? ''));
        foreach ([$start, $end] as $d) {
            $dt = \DateTime::createFromFormat('Y-m-d', $d);
            if ($dt === false || $dt->format('Y-m-d') !== $d) {
                return ServiceResult::fail('Fechas inválidas.');
            }
        }
        if ($end < $start) {
            return ServiceResult::fail('La fecha final no puede ser anterior a la inicial.');
        }
        if ($this->vacations->userOverlaps($userId, $start, $end)) {
            return ServiceResult::fail('Ya hay vacaciones o una falta justificada para esa persona en esas fechas.');
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if ($type === 'justified' && $notes === '') {
            return ServiceResult::fail('Indica el motivo de la falta justificada.');
        }
        $this->vacations->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'start_date' => $start,
            'end_date'   => $end,
            'notes'      => $notes !== '' ? mb_substr($notes, 0, 500) : null,
            'created_by' => $supervisorId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ServiceResult::ok(null, $type === 'justified' ? 'Falta justificada registrada.' : 'Vacaciones registradas.');
    }

    public function deleteVacation(int $id, int $supervisorId): ServiceResult
    {
        if (! $this->isSupervisor($supervisorId)) {
            return ServiceResult::fail('No autorizado.');
        }
        if ($this->vacations->find($id) === null) {
            return ServiceResult::fail('Periodo no encontrado.');
        }
        $this->vacations->delete($id);

        return ServiceResult::ok(null, 'Periodo eliminado.');
    }

    /** @return array<int,array<string,string>> [user_id][Y-m-d] => period type for excused days in the range. */
    private function vacationDaysMap(string $start, string $end): array
    {
        $map = [];
        foreach ($this->vacations->overlapping($start, $end) as $v) {
            $from = max((string) $v['start_date'], $start);
            $to   = min((string) $v['end_date'], $end);
            for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                $map[(int) $v['user_id']][$d] = (string) $v['type'];
            }
        }

        return $map;
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

        $roster    = $this->roster();
        $logs      = $this->logs->rangeWithUsers($monday, $sunday);
        $vacations = $this->vacationDaysMap($monday, $sunday);

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
                    'excused_type' => $log === null ? ($vacations[$agent['id']][$day] ?? null) : null,
                    'is_absent'    => $log === null && $day < $today && $this->isWeekday($day) && ! isset($vacations[$agent['id']][$day]),
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
     * Each row also carries the day-by-day detail: late_days (date, check_in
     * H:i, minutes_late over the expected time) and absence_days (dates).
     *
     * @return array<int,array{user_id:int,name:string,late_count:int,absence_count:int,late_days:array<int,array{date:string,check_in:string,minutes_late:int}>,absence_days:string[]}>
     */
    public function incidencesSummary(string $start, string $end): array
    {
        $roster    = $this->roster();
        $logs      = $this->logs->rangeWithUsers($start, $end);
        $vacations = $this->vacationDaysMap($start, $end);

        $byUserDay = [];
        foreach ($logs as $l) {
            $byUserDay[(int) $l['user_id']][(string) $l['work_date']] = $l;
        }

        [$expH, $expM] = array_map('intval', explode(':', $this->expectedCheckin()));
        $toleranceSec  = $this->toleranceMinutes() * 60;
        $today         = date('Y-m-d');

        $out = [];
        foreach ($roster as $agent) {
            $lateDays   = [];
            $absentDays = [];
            for ($d = $start; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                if ($d > $today) {
                    continue;
                }
                $log = $byUserDay[$agent['id']][$d] ?? null;
                if ($log === null) {
                    if ($this->isWeekday($d) && ! isset($vacations[$agent['id']][$d])) {
                        $absentDays[] = $d;
                    }
                    continue;
                }
                if ($log['check_in_at'] === null) {
                    continue;
                }
                $checkInTs = strtotime($log['check_in_at']);
                $limitTs   = strtotime($d . sprintf(' %02d:%02d:00', $expH, $expM)) + $toleranceSec;
                if ($checkInTs > $limitTs) {
                    $expectedTs = $limitTs - $toleranceSec;
                    $lateDays[] = [
                        'date'         => $d,
                        'check_in'     => date('H:i', $checkInTs),
                        'minutes_late' => (int) round(($checkInTs - $expectedTs) / 60),
                    ];
                }
            }
            if ($lateDays !== [] || $absentDays !== []) {
                $out[] = [
                    'user_id'       => $agent['id'],
                    'name'          => $agent['name'],
                    'late_count'    => count($lateDays),
                    'absence_count' => count($absentDays),
                    'late_days'     => $lateDays,
                    'absence_days'  => $absentDays,
                ];
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
