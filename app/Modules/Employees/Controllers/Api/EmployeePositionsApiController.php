<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeePositionsApiController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $svc = service('employeeCatalogService');

        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 50);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 50;

        $items = $svc->paginatePositions($perPage, max(1, $page));
        $total = $svc->totalPositions();

        return $this->successPaginated($items, $this->buildMeta($total, max(1, $page), $perPage));
    }

    public function show($id = null): ResponseInterface
    {
        $position = service('employeeCatalogService')->findPosition((int) $id);

        if (! $position) {
            return $this->notFound('Puesto no encontrado.');
        }

        return $this->success($position);
    }

    public function create(): ResponseInterface
    {
        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = service('employeeCatalogService')->createPosition((array) $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated($result->data);
    }

    public function update($id = null): ResponseInterface
    {
        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = service('employeeCatalogService')->updatePosition((int) $id, (array) $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success($result->data, $result->message);
    }

    public function delete($id = null): ResponseInterface
    {
        $result = service('employeeCatalogService')->destroyPosition((int) $id);

        if (! $result->success) {
            return $this->error(is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors, 422);
        }

        return $this->success(null, $result->message);
    }
}
