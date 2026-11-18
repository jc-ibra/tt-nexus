<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API for HR document requests.
 * Upload endpoint is used by the Intranet after the employee submits a file.
 */
class EmployeeDocumentsApiController extends BaseController
{
    public function upload(int $requestId): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];
        $result = service('employeeDocumentService')->submitUpload($requestId, [
            'nexus_id'       => (string) ($body['nexus_id'] ?? ''),
            'original_name'  => (string) ($body['original_name'] ?? ''),
            'mime'           => (string) ($body['mime'] ?? ''),
            'content_base64' => (string) ($body['content_base64'] ?? ''),
        ]);

        if (! $result->success) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => $result->message,
                'errors'  => $result->errors,
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => $result->message,
            'data'    => $result->data,
        ]);
    }

    public function show(int $requestId): ResponseInterface
    {
        $req = service('employeeDocumentService')->findRequest($requestId);
        if (! $req) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Solicitud no encontrada.',
            ]);
        }

        unset($req['stored_name']);

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $req,
        ]);
    }
}
