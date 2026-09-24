<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Model;

/**
 * One row per agent per calendar day: check-in/out and the scheme worked
 * under (presencial / home_office / permiso).
 */
class AttendanceLogModel extends Model
{
    protected $table         = 'servicedesk_attendance_logs';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id', 'work_date', 'scheme', 'check_in_at', 'check_out_at', 'permit_id',
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function todayForUser(int $userId, string $workDate): ?array
    {
        return $this->where('user_id', $userId)->where('work_date', $workDate)->first();
    }

    /**
     * All logs in a date range, joined with the user's name, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function rangeWithUsers(string $start, string $end): array
    {
        return $this->db->table('servicedesk_attendance_logs l')
            ->select('l.*, u.name AS user_name, p.status AS permit_status')
            ->join('core_users u', 'u.id = l.user_id')
            ->join('servicedesk_attendance_permits p', 'p.id = l.permit_id', 'left')
            ->where('l.work_date >=', $start)
            ->where('l.work_date <=', $end)
            ->orderBy('l.work_date', 'DESC')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();
    }

    public function forUserRange(int $userId, string $start, string $end): array
    {
        return $this->where('user_id', $userId)
            ->where('work_date >=', $start)
            ->where('work_date <=', $end)
            ->orderBy('work_date', 'ASC')
            ->findAll();
    }
}
