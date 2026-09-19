<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use CodeIgniter\Model;

class ReportDeliveryModel extends Model
{
    protected $table         = 'reports_deliveries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'snapshot_id',
        'recipients',
        'status',
        'error',
        'sent_by',
        'sent_at',
    ];

    public function forSnapshot(int $snapshotId): array
    {
        return $this->where('snapshot_id', $snapshotId)->orderBy('sent_at', 'DESC')->findAll();
    }
}
