<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Model;

class MsLicenseModel extends Model
{
    protected $table         = 'provisioning_ms_licenses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'code', 'description', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    public function getAllActive(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    public function listAll(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function findByCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }
}
