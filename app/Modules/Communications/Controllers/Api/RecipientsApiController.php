<?php

declare(strict_types=1);

namespace App\Modules\Communications\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;

class RecipientsApiController extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);
        $svc     = service('recipientService');
        $data    = $svc->paginate($perPage);

        return $this->successPaginated(
            $data['recipients'],
            $this->buildMeta($svc->total(), $page, $perPage)
        );
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $recipient = service('recipientService')->findById((int) $id);

        if ($recipient === null) {
            return $this->notFound('Destinatario no encontrado.');
        }

        return $this->success($recipient);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('recipientService')->create($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated($result->data);
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('recipientService')->update((int) $id, $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success($result->data);
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('recipientService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        return $this->response->setStatusCode(204)->setBody('');
    }

    public function import(): \CodeIgniter\HTTP\ResponseInterface
    {
        $file   = $this->request->getFile('file');
        $listId = $this->request->getPost('list_id') ? (int) $this->request->getPost('list_id') : null;

        if ($file === null || ! $file->isValid()) {
            return $this->error('No se recibió un archivo válido.', 400);
        }

        $result = service('recipientService')->importFromFile($file, $listId);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        return $this->success($result->data);
    }
}
