<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'email', 'password', 'status'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    protected $validationRules = [
        'name'  => 'required|max_length[120]',
        'email' => 'required|valid_email|max_length[191]|is_unique[users.email,id,{id}]',
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

        $roles = $this->db->table('user_roles ur')
            ->select('r.id, r.name')
            ->join('roles r', 'r.id = ur.role_id')
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
            $user['roles'] = $this->db->table('user_roles ur')
                ->select('r.name')
                ->join('roles r', 'r.id = ur.role_id')
                ->where('ur.user_id', $user['id'])
                ->get()->getResultArray();
        }

        return $users;
    }
}
