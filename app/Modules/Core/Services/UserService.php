<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\UserModel;
use App\Modules\Core\Models\UserRoleModel;

class UserService
{
    public function __construct(
        private UserModel $userModel,
        private UserRoleModel $userRoleModel
    ) {}

    public function paginate(int $perPage = 20): array
    {
        $users = $this->userModel->listWithRoles($perPage);
        $pager = $this->userModel->pager;

        return compact('users', 'pager');
    }

    public function total(): int
    {
        return $this->userModel->countAll();
    }

    public function findById(int $id): ?array
    {
        return $this->userModel->withRoles($id);
    }

    public function create(array $data): ServiceResult
    {
        $rules = [
            'name'     => 'required|max_length[120]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]',
            'role_ids' => 'required',
        ];

        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $userId = $this->userModel->insert([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status'   => $data['status'] ?? 'active',
        ]);

        if ($userId === false) {
            return ServiceResult::fail('Error al crear el usuario.');
        }

        $roleIds = is_array($data['role_ids']) ? $data['role_ids'] : [$data['role_ids']];
        $this->userRoleModel->syncRoles((int) $userId, $roleIds);

        return ServiceResult::ok($this->userModel->withRoles((int) $userId));
    }

    public function update(int $id, array $data): ServiceResult
    {
        $user = $this->userModel->find($id);

        if ($user === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        $rules = [
            'name'  => 'required|max_length[120]',
            'email' => "required|valid_email|is_unique[users.email,id,{$id}]",
        ];

        if (! empty($data['password'])) {
            $rules['password'] = 'min_length[8]';
        }

        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $updateData = [
            'name'   => $data['name'],
            'email'  => $data['email'],
            'status' => $data['status'] ?? $user['status'],
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->userModel->update($id, $updateData);

        if (isset($data['role_ids'])) {
            $roleIds = is_array($data['role_ids']) ? $data['role_ids'] : [$data['role_ids']];
            $this->userRoleModel->syncRoles($id, $roleIds);
        }

        return ServiceResult::ok($this->userModel->withRoles($id));
    }

    public function destroy(int $id): ServiceResult
    {
        if ($this->userModel->find($id) === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        // Prevent deleting yourself
        if (session()->get('user_id') === $id) {
            return ServiceResult::fail('No puedes eliminar tu propio usuario.');
        }

        $this->userModel->delete($id);

        return ServiceResult::ok();
    }
}
