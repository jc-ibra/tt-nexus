<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table         = 'core_roles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'description', 'status'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    public function getAllActive(): array
    {
        return $this->where('status', 'active')->orderBy('name')->findAll();
    }

    public function withModules(int $id): ?array
    {
        $role = $this->find($id);

        if ($role === null) {
            return null;
        }

        $modules = $this->db->table('core_role_modules rm')
            ->select('m.id, m.key, m.name')
            ->join('core_modules m', 'm.id = rm.module_id')
            ->where('rm.role_id', $id)
            ->get()->getResultArray();

        $role['modules']    = $modules;
        $role['module_ids'] = array_column($modules, 'id');

        return $role;
    }

    public function syncModules(int $roleId, array $moduleIds): void
    {
        $this->db->table('core_role_modules')->where('role_id', $roleId)->delete();

        foreach ($moduleIds as $moduleId) {
            $this->db->table('core_role_modules')->insert([
                'role_id'   => $roleId,
                'module_id' => (int) $moduleId,
            ]);
        }
    }
}
