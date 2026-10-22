<?php

declare(strict_types=1);

namespace App\Modules\Shares\Models;

use CodeIgniter\Model;

class ShareFileModel extends Model
{
    protected $table         = 'shares_files';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'owner_id', 'title', 'original_name', 'stored_name', 'size_bytes', 'sha256', 'created_at', 'updated_at',
    ];

    /** Files newest first with owner name and link count. $ownerId null = all owners. */
    public function listFor(?int $ownerId): array
    {
        $b = $this->select('shares_files.*, core_users.name AS owner_name, (SELECT COUNT(*) FROM shares_links l WHERE l.file_id = shares_files.id) AS link_count')
            ->join('core_users', 'core_users.id = shares_files.owner_id', 'left')
            ->orderBy('shares_files.id', 'DESC');
        if ($ownerId !== null) {
            $b->where('shares_files.owner_id', $ownerId);
        }

        return $b->findAll();
    }
}
