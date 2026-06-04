<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers\Api;

class UsersApiController extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $svc   = service('userService');
        $data  = $svc->paginate($perPage);
        $total = $svc->total();

        return $this->successPaginated(
            $data['users'],
            $this->buildMeta($total, $page, $perPage)
        );
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $user = service('userService')->findById((int) $id);

        if ($user === null) {
            return $this->notFound('Usuario no encontrado.');
        }

        unset($user['password']);

        return $this->success($user);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('userService')->create($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        $user = $result->data;
        unset($user['password']);

        return $this->successCreated($user);
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('userService')->update((int) $id, $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        $user = $result->data;
        unset($user['password']);

        return $this->success($user);
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('userService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        return $this->response->setStatusCode(204)->setBody('');
    }
}
