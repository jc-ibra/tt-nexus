<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\RoleModel;

class RoleService
{
    public function __construct(private RoleModel $roleModel) {}

    public function paginate(int $perPage = 20): array
    {
        $roles = $this->roleModel->paginate($perPage);
        $pager = $this->roleModel->pager;

        return compact('roles', 'pager');
    }

    public function findById(int $id): ?array
    {
        return $this->roleModel->withModules($id);
    }

    public function create(array $data): ServiceResult
    {
        $rules = ['name' => 'required|max_length[80]|is_unique[roles.name]'];
        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $roleId = $this->roleModel->insert([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'active',
        ]);

        if ($roleId === false) {
            return ServiceResult::fail('Error al crear el rol.');
        }

        $moduleIds = $data['module_ids'] ?? [];

        if (! empty($moduleIds)) {
            $this->roleModel->syncModules((int) $roleId, $moduleIds);
        }

        return ServiceResult::ok($this->roleModel->withModules((int) $roleId));
    }

    public function update(int $id, array $data): ServiceResult
    {
        if ($this->roleModel->find($id) === null) {
            return ServiceResult::fail('Rol no encontrado.');
        }

        $rules = ['name' => "required|max_length[80]|is_unique[roles.name,id,{$id}]"];
        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $this->roleModel->update($id, [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'active',
        ]);

        $moduleIds = $data['module_ids'] ?? [];
        $this->roleModel->syncModules($id, $moduleIds);

        return ServiceResult::ok($this->roleModel->withModules($id));
    }

    public function destroy(int $id): ServiceResult
    {
        if ($this->roleModel->find($id) === null) {
            return ServiceResult::fail('Rol no encontrado.');
        }

        // Check if any users have this role
        $count = db_connect()->table('user_roles')->where('role_id', $id)->countAllResults();

        if ($count > 0) {
            return ServiceResult::fail("No se puede eliminar: {$count} usuario(s) tienen este rol asignado.");
        }

        $this->roleModel->delete($id);

        return ServiceResult::ok();
    }
}
