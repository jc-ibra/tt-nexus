<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Model;

class ProvisioningSystemModel extends Model
{
    protected $table         = 'provisioning_systems';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'key', 'name', 'description', 'base_url', 'auth_type',
        'options', 'notes', 'is_active',
    ];

    public function findByKey(string $key): ?array
    {
        return $this->where('key', $key)->first();
    }

    public function listAll(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function listActive(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    public function getOptions(array $system): array
    {
        if (empty($system['options'])) {
            return [];
        }
        $decoded = json_decode((string) $system['options'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
