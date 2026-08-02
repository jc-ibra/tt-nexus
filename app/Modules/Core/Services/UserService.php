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
            'name'         => 'required|max_length[120]',
            'email'        => 'required|valid_email|is_unique[core_users.email]',
            'password'     => 'required|min_length[8]',
            'role_ids'     => 'required',
            'glpi_user_id' => 'permit_empty|is_natural_no_zero',
        ];

        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $userId = $this->userModel->insert([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => password_hash($data['password'], PASSWORD_DEFAULT),
            'status'       => $data['status'] ?? 'active',
            'glpi_user_id' => $this->normalizeGlpiUserId($data['glpi_user_id'] ?? null),
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
            'name'         => 'required|max_length[120]',
            'email'        => "required|valid_email|is_unique[core_users.email,id,{$id}]",
            'glpi_user_id' => 'permit_empty|is_natural_no_zero',
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

        // Only touch the GLPI mapping when the field was submitted (web form and
        // API always send it; partial API updates that omit it leave it intact).
        if (array_key_exists('glpi_user_id', $data)) {
            $updateData['glpi_user_id'] = $this->normalizeGlpiUserId($data['glpi_user_id']);
        }

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

    /**
     * Normalizes the optional GLPI user id to a positive int or null. Empty
     * string / 0 / non-numeric all mean "unmapped".
     */
    private function normalizeGlpiUserId(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
