<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Model;

/**
 * "Permiso" requests: an agent marked home_office on a day scheduled
 * presencial. Created pending at check-in time; the attendance supervisor
 * approves/rejects it retroactively (never blocks the check-in itself).
 */
class AttendancePermitModel extends Model
{
    protected $table         = 'servicedesk_attendance_permits';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id', 'work_date', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];
    protected $useTimestamps = false; // created_at is set explicitly on insert
    protected $returnType    = 'array';

    /** @return array<int,array<string,mixed>> pending permits, oldest first, with the requester's name. */
    public function pendingWithUsers(): array
    {
        return $this->db->table('servicedesk_attendance_permits p')
            ->select('p.*, u.name AS user_name')
            ->join('core_users u', 'u.id = p.user_id')
            ->where('p.status', 'pending')
            ->orderBy('p.work_date', 'ASC')
            ->get()->getResultArray();
    }

    /** @return array<int,array<string,mixed>> permits in a date range, newest first, with names. */
    public function rangeWithUsers(string $start, string $end): array
    {
        return $this->db->table('servicedesk_attendance_permits p')
            ->select('p.*, u.name AS user_name, r.name AS reviewer_name')
            ->join('core_users u', 'u.id = p.user_id')
            ->join('core_users r', 'r.id = p.reviewed_by', 'left')
            ->where('p.work_date >=', $start)
            ->where('p.work_date <=', $end)
            ->orderBy('p.work_date', 'DESC')
            ->get()->getResultArray();
    }

    public function review(int $id, string $status, int $reviewerId, ?string $notes): bool
    {
        return $this->update($id, [
            'status'       => $status,
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $notes !== null ? mb_substr($notes, 0, 500) : null,
        ]);
    }
}
