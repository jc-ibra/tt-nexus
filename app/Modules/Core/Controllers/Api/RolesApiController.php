<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers\Api;

use App\Modules\Core\Models\RoleModel;

class RolesApiController extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $model = new RoleModel();
        $roles = $model->paginate($perPage);
        $total = $model->countAll();

        return $this->successPaginated($roles, $this->buildMeta($total, $page, $perPage));
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $role = service('roleService')->findById((int) $id);

        if ($role === null) {
            return $this->notFound('Rol no encontrado.');
        }

        return $this->success($role);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('roleService')->create($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated($result->data);
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('roleService')->update((int) $id, $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success($result->data);
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('roleService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        return $this->response->setStatusCode(204)->setBody('');
    }
}
