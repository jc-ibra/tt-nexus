<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'core_users';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'email', 'password', 'status', 'mfa_secret', 'mfa_enabled', 'glpi_user_id'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    // The `id` rule is not cosmetic: CI4 only fills the `{id}` placeholder when
    // `id` is present in the written data AND has its own rule. Without it the
    // rule stays literal, is_unique matches the row being edited and update()
    // silently returns false. UserService::update() passes the id for this.
    protected $validationRules = [
        'id'    => 'permit_empty|is_natural_no_zero',
        'name'  => 'required|max_length[120]',
        'email' => 'required|valid_email|max_length[191]|is_unique[core_users.email,id,{id}]',
    ];

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    public function withRoles(int $id): ?array
    {
        $user = $this->find($id);

        if ($user === null) {
            return null;
        }

        $roles = $this->db->table('core_user_roles ur')
            ->select('r.id, r.name')
            ->join('core_roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $id)
            ->get()->getResultArray();

        $user['roles']    = $roles;
        $user['role_ids'] = array_column($roles, 'id');

        return $user;
    }

    public function listWithRoles(int $perPage = 20): array
    {
        $users = $this->paginate($perPage);

        foreach ($users as &$user) {
            $user['roles'] = $this->db->table('core_user_roles ur')
                ->select('r.name')
                ->join('core_roles r', 'r.id = ur.role_id')
                ->where('ur.user_id', $user['id'])
                ->get()->getResultArray();
        }

        return $users;
    }
}
