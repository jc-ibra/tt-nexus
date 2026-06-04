<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

abstract class BaseApiController extends ResourceController
{
    protected $format = 'json';

    protected function success(mixed $data, int $code = ResponseInterface::HTTP_OK): ResponseInterface
    {
        return $this->respond(['status' => 'success', 'data' => $data], $code);
    }

    protected function successCreated(mixed $data): ResponseInterface
    {
        return $this->respond(['status' => 'success', 'data' => $data], ResponseInterface::HTTP_CREATED);
    }

    protected function successPaginated(array $data, array $meta): ResponseInterface
    {
        return $this->respond(['status' => 'success', 'data' => $data, 'meta' => $meta]);
    }

    protected function error(string $message, int $code = ResponseInterface::HTTP_BAD_REQUEST): ResponseInterface
    {
        return $this->respond(['status' => 'error', 'message' => $message], $code);
    }

    protected function validationError(array $errors): ResponseInterface
    {
        return $this->respond([
            'status'  => 'error',
            'message' => 'Validation failed',
            'errors'  => $errors,
        ], ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
    }

    protected function notFound(string $message = 'Resource not found'): ResponseInterface
    {
        return $this->respond(['status' => 'error', 'message' => $message], ResponseInterface::HTTP_NOT_FOUND);
    }

    protected function buildMeta(int $total, int $page, int $perPage): array
    {
        return [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }
}
