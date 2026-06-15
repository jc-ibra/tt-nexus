<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class ModuleModel extends Model
{
    protected $table         = 'core_modules';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['key', 'name', 'description', 'route_base', 'icon', 'is_active'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    public function getActiveByKeys(array $keys): array
    {
        return $this->whereIn('key', $keys)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getAllActive(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
