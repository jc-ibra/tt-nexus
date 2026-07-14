<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Model;

class ServiceDeskImportModel extends Model
{
    protected $table         = 'servicedesk_imports';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'source_filename',
        'source_path',
        'output_path',
        'log_path',
        'status',
        'total_rows',
        'processed_rows',
        'succeeded_rows',
        'failed_rows',
        'error_message',
        'uploaded_by',
        'started_at',
        'finished_at',
    ];

    /**
     * Oldest pending job, for the worker to pick up (FIFO).
     */
    public function nextPending(): ?array
    {
        return $this->where('status', 'pending')->orderBy('id', 'ASC')->first();
    }

    /**
     * Recent jobs for the history view, with the requesting Nexus user's name
     * (uploaded_by_name; null for API/system imports).
     */
    public function recent(int $limit = 50, ?int $userId = null): array
    {
        $builder = $this
            ->select('servicedesk_imports.*, core_users.name AS uploaded_by_name, core_users.email AS uploaded_by_email')
            ->join('core_users', 'core_users.id = servicedesk_imports.uploaded_by', 'left')
            ->orderBy('servicedesk_imports.id', 'DESC');
        if ($userId !== null) {
            $builder->where('servicedesk_imports.uploaded_by', $userId);
        }
        return $builder->findAll($limit);
    }

    /**
     * A single job joined with the requesting user's name (for the detail view).
     */
    public function findWithUser(int $id): ?array
    {
        return $this
            ->select('servicedesk_imports.*, core_users.name AS uploaded_by_name, core_users.email AS uploaded_by_email')
            ->join('core_users', 'core_users.id = servicedesk_imports.uploaded_by', 'left')
            ->where('servicedesk_imports.id', $id)
            ->first();
    }
}
