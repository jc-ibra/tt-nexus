<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Models;

use CodeIgniter\Model;

class PresentationVersionModel extends Model
{
    protected $table         = 'presentations_versions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'original_name', 'stored_name', 'size_bytes', 'sha256', 'notes', 'uploaded_by', 'created_at',
    ];

    /** Versions newest first, with the uploader's name. */
    public function listWithUploader(): array
    {
        return $this->select('presentations_versions.*, core_users.name AS uploader_name')
            ->join('core_users', 'core_users.id = presentations_versions.uploaded_by', 'left')
            ->orderBy('presentations_versions.id', 'DESC')
            ->findAll();
    }

    public function findWithUploader(int $id): ?array
    {
        return $this->select('presentations_versions.*, core_users.name AS uploader_name')
            ->join('core_users', 'core_users.id = presentations_versions.uploaded_by', 'left')
            ->where('presentations_versions.id', $id)
            ->first();
    }

    public function latest(): ?array
    {
        return $this->select('presentations_versions.*, core_users.name AS uploader_name')
            ->join('core_users', 'core_users.id = presentations_versions.uploaded_by', 'left')
            ->orderBy('presentations_versions.id', 'DESC')
            ->first();
    }
}
