<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class UserRoleModel extends Model
{
    protected $table         = 'core_user_roles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'role_id'];
    protected $useTimestamps  = false;
    protected $returnType    = 'array';

    public function getRolesForUser(int $userId): array
    {
        return $this->db->table('core_user_roles ur')
            ->select('r.*')
            ->join('core_roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.status', 'active')
            ->get()->getResultArray();
    }

    public function getModuleKeysForUser(int $userId): array
    {
        $rows = $this->db->table('core_user_roles ur')
            ->select('m.key')
            ->join('core_role_modules rm', 'rm.role_id = ur.role_id')
            ->join('core_modules m', 'm.id = rm.module_id')
            ->where('ur.user_id', $userId)
            ->where('m.is_active', 1)
            ->get()->getResultArray();

        return array_column($rows, 'key');
    }

    public function getModuleKeysForRole(int $roleId): array
    {
        $rows = $this->db->table('core_role_modules rm')
            ->select('m.key')
            ->join('core_modules m', 'm.id = rm.module_id')
            ->where('rm.role_id', $roleId)
            ->where('m.is_active', 1)
            ->get()->getResultArray();

        return array_column($rows, 'key');
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->where('user_id', $userId)->delete();

        foreach ($roleIds as $roleId) {
            $this->insert(['user_id' => $userId, 'role_id' => (int) $roleId]);
        }
    }
}
