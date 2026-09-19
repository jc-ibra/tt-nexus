<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use CodeIgniter\Model;

class ReportSnapshotVersionModel extends Model
{
    protected $table         = 'reports_snapshot_versions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'snapshot_id',
        'payload_json',
        'total_tickets',
        'replaced_by',
    ];

    public function versionsFor(int $snapshotId): array
    {
        return $this->where('snapshot_id', $snapshotId)->orderBy('created_at', 'DESC')->findAll();
    }
}
