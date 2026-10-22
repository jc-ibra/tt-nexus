<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Model;

/**
 * Excused periods (inclusive date ranges) per agent: vacation or justified absence.
 */
class AttendanceVacationModel extends Model
{
    protected $table         = 'servicedesk_attendance_vacations';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'type', 'start_date', 'end_date', 'notes', 'created_by'];
    protected $useTimestamps = false; // created_at is set explicitly on insert
    protected $returnType    = 'array';

    /** @return array<int,array<string,mixed>> periods overlapping the range, with names, newest first. */
    public function overlapping(string $start, string $end): array
    {
        return $this->db->table('servicedesk_attendance_vacations v')
            ->select('v.*, u.name AS user_name')
            ->join('core_users u', 'u.id = v.user_id')
            ->where('v.start_date <=', $end)
            ->where('v.end_date >=', $start)
            ->orderBy('v.start_date', 'DESC')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();
    }

    public function userOverlaps(int $userId, string $start, string $end): bool
    {
        return $this->where('user_id', $userId)
            ->where('start_date <=', $end)
            ->where('end_date >=', $start)
            ->countAllResults() > 0;
    }
}
