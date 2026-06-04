<?php

namespace App\Modules\Communications\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;

class CommunicationsApiController extends BaseApiController
{
    public function index()
    {
        $svc   = service('communicationService');
        $page  = (int) ($this->request->getGet('page') ?? 1);
        $limit = (int) ($this->request->getGet('limit') ?? 20);
        $total = $svc->total();
        $items = $svc->paginate($limit);

        return $this->successPaginated($items, $this->buildMeta($total, $limit, $page));
    }

    public function show($id = null)
    {
        $comm = service('communicationService')->findById((int) $id);

        if (! $comm) {
            return $this->notFound('Comunicado no encontrado.');
        }

        return $this->success($comm);
    }

    public function create()
    {
        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = service('communicationService')->create($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated($result->data);
    }

    public function update($id = null)
    {
        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = service('communicationService')->update((int) $id, $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->success($result->data, $result->message);
    }

    public function delete($id = null)
    {
        $result = service('communicationService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error($result->errors, 422);
        }

        return $this->success(null, $result->message);
    }

    public function preview($id = null)
    {
        $result = service('communicationService')->renderPreview((int) $id);

        if (! $result->success) {
            return $this->notFound('Comunicado no encontrado.');
        }

        return $this->success($result->data);
    }

    public function sendTest($id = null)
    {
        $svc   = service('communicationService');
        $comm  = $svc->findById((int) $id);
        $body  = $this->request->getJSON(true) ?? $this->request->getPost();
        $email = $body['email'] ?? '';

        if (! $comm) {
            return $this->notFound('Comunicado no encontrado.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->validationError(['email' => 'Correo no válido.']);
        }

        $mailer = \Config\Services::email();
        $mailer->setFrom($comm['from_email'], $comm['from_name']);
        $mailer->setTo($email);
        $mailer->setSubject('[TEST] ' . $comm['subject']);
        $mailer->setMessage($svc->buildEmailHtml($comm['subject'], $comm['body']));
        $mailer->setMailType('html');

        if ($mailer->send()) {
            return $this->success(null, 'Correo de prueba enviado a ' . $email);
        }

        return $this->error('Error al enviar el correo de prueba.', 500);
    }

    public function send($id = null)
    {
        $result = service('communicationService')->queueForSending((int) $id);

        if (! $result->success) {
            return $this->error($result->errors, 422);
        }

        return $this->success($result->data, $result->message);
    }

    public function logs($id = null)
    {
        $svc  = service('communicationService');
        $comm = $svc->findById((int) $id);

        if (! $comm) {
            return $this->notFound('Comunicado no encontrado.');
        }

        $page  = (int) ($this->request->getGet('page') ?? 1);
        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $data  = $svc->getLogs((int) $id, $limit);

        return $this->successPaginated($data['logs'], array_merge(
            $this->buildMeta((int) $data['stats']['total'], $limit, $page),
            ['stats' => $data['stats']]
        ));
    }
}
