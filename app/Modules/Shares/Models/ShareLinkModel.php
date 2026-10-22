<?php

declare(strict_types=1);

namespace App\Modules\Shares\Models;

use CodeIgniter\Model;

class ShareLinkModel extends Model
{
    protected $table         = 'shares_links';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'file_id', 'token', 'label', 'is_active', 'expires_at', 'max_views', 'view_count',
        'password_hash', 'last_viewed_at', 'created_by', 'created_at', 'updated_at',
    ];

    private function base()
    {
        return $this->select('shares_links.*, shares_files.title AS file_title, shares_files.owner_id, core_users.name AS owner_name')
            ->join('shares_files', 'shares_files.id = shares_links.file_id')
            ->join('core_users', 'core_users.id = shares_files.owner_id', 'left');
    }

    /** Links newest first. $ownerId null = all owners. */
    public function listFor(?int $ownerId): array
    {
        $b = $this->base()->orderBy('shares_links.id', 'DESC');
        if ($ownerId !== null) {
            $b->where('shares_files.owner_id', $ownerId);
        }

        return $b->findAll();
    }

    public function findWithFile(int $id): ?array
    {
        return $this->base()->where('shares_links.id', $id)->first();
    }

    public function findByToken(string $token): ?array
    {
        return $this->base()->where('shares_links.token', $token)->first();
    }
}
