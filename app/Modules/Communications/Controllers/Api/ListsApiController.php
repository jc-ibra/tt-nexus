<?php

declare(strict_types=1);

namespace App\Modules\Communications\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;

class ListsApiController extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);
        $svc     = service('listService');
        $data    = $svc->paginate($perPage);

        return $this->successPaginated(
            $data['lists'],
            $this->buildMeta($svc->total(), $page, $perPage)
        );
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $list = service('listService')->findWithRecipients((int) $id);

        if ($list === null) {
            return $this->notFound('Lista no encontrada.');
        }

        return $this->success($list);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('listService')->create($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated($result->data);
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('listService')->update((int) $id, $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success($result->data);
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('listService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        return $this->response->setStatusCode(204)->setBody('');
    }
}
