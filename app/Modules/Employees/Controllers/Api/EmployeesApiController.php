<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeesApiController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $svc = service('employeeService');

        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 20;

        $filters = [
            'q'             => trim((string) ($this->request->getGet('q') ?? '')),
            'area_id'       => $this->request->getGet('area_id'),
            'department_id' => $this->request->getGet('department_id'),
            'position_id'   => $this->request->getGet('position_id'),
            'active'        => $this->request->getGet('active'),
        ];

        $items = $svc->paginate($filters, $perPage, max(1, $page));
        $total = $svc->total($filters);

        return $this->successPaginated($items, $this->buildMeta($total, max(1, $page), $perPage));
    }

    public function show($id = null): ResponseInterface
    {
        $employee = service('employeeService')->findById((int) $id);

        if (! $employee) {
            return $this->notFound('Empleado no encontrado.');
        }

        return $this->success($employee);
    }

    public function create(): ResponseInterface
    {
        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = service('employeeService')->create((array) $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated($result->data);
    }

    public function update($id = null): ResponseInterface
    {
        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = service('employeeService')->update((int) $id, (array) $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success($result->data, $result->message);
    }

    public function delete($id = null): ResponseInterface
    {
        $result = service('employeeService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error(is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors, 422);
        }

        return $this->response->setStatusCode(204)->setBody('');
    }

    public function search(): ResponseInterface
    {
        $term  = trim((string) ($this->request->getGet('q') ?? ''));
        $limit = (int) ($this->request->getGet('limit') ?? 20);
        $limit = $limit > 0 && $limit <= 100 ? $limit : 20;

        $excludeId = $this->request->getGet('exclude_id') !== null
            ? (int) $this->request->getGet('exclude_id')
            : null;

        if ($term === '') {
            return $this->success([]);
        }

        return $this->success(service('employeeService')->search($term, $limit, $excludeId));
    }

    public function uploadPhoto($id = null): ResponseInterface
    {
        $file = $this->request->getFile('photo');
        if (! $file) {
            return $this->error('No se recibió ningún archivo.', 400);
        }

        $result = service('employeeService')->saveUploadedPhoto((int) $id, $file);

        if (! $result->success) {
            return $this->error(is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors, 422);
        }

        return $this->success($result->data, $result->message);
    }
}
