<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class SyncRunModel extends Model
{
    protected $table         = 'maildispatch_sync_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // append-only run log

    protected $allowedFields = [
        'mailbox_address',
        'status',
        'trigger',
        'processed',
        'created',
        'updated',
        'errors',
        'message',
        'duration_ms',
    ];

    /** Most recent runs for the admin status panel. */
    public function recent(int $limit = 15): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }
}
