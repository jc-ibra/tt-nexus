<?php

declare(strict_types=1);

namespace App\Modules\Buzones\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;

class BuzonesApiController extends BaseApiController
{
    private function svc(): \App\Modules\Buzones\Services\BuzonesService
    {
        return service('buzonesService');
    }

    // GET /api/v1/buzones/mailboxes
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = $this->svc()->listMailboxes();

        if (! $result->success) {
            return $this->error($result->message, 502);
        }

        return $this->success($result->data);
    }

    // GET /api/v1/buzones/mailboxes/{email}
    public function show($email = null): \CodeIgniter\HTTP\ResponseInterface
    {
        if (empty($email)) {
            return $this->error('Email requerido.', 400);
        }

        $result = $this->svc()->getMailbox(urldecode((string) $email));

        if (! $result->success) {
            return $this->error($result->message, 502);
        }

        return $this->success($result->data);
    }

    // POST /api/v1/buzones/mailboxes
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = $this->svc()->createMailbox($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        return $this->successCreated(['message' => $result->message]);
    }

    // POST /api/v1/buzones/mailboxes/edit
    public function editMailbox(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data  = (array) $this->request->getJSON(true);
        $email = trim($data['email'] ?? '');

        if (empty($email)) {
            return $this->error('El campo email es requerido.');
        }

        $result = $this->svc()->editMailbox($email, $data);

        if (! $result->success) {
            return $this->validationError($result->errors ?: [$result->message]);
        }

        return $this->success(['message' => $result->message]);
    }

    // POST /api/v1/buzones/mailboxes/delete
    public function deleteMailboxes(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $emails = (array) ($data['emails'] ?? []);

        $result = $this->svc()->deleteMailboxes($emails);

        if (! $result->success) {
            return $this->error($result->message);
        }

        return $this->success(['message' => $result->message]);
    }

    // POST /api/v1/buzones/mailboxes/toggle
    public function toggleMailbox(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $email  = trim($data['email'] ?? '');
        $active = (bool) ($data['active'] ?? false);

        if (empty($email)) {
            return $this->error('El campo email es requerido.');
        }

        $result = $this->svc()->toggleMailbox($email, $active);

        if (! $result->success) {
            return $this->error($result->message);
        }

        return $this->success(['message' => $result->message]);
    }

    // GET /api/v1/buzones/domains
    public function domains(): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = $this->svc()->getDomains();

        if (! $result->success) {
            return $this->error($result->message, 502);
        }

        return $this->success($result->data);
    }

    // GET /api/v1/buzones/settings
    public function getSettings(): \CodeIgniter\HTTP\ResponseInterface
    {
        $settings = $this->svc()->getSettings();
        // Mask the API key
        if (! empty($settings['mailcow_api_key'])) {
            $settings['mailcow_api_key'] = str_repeat('*', 8) . substr($settings['mailcow_api_key'], -4);
        }
        return $this->success($settings);
    }

    // POST /api/v1/buzones/settings
    public function saveSettings(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = $this->svc()->saveSettings($data);

        if (! $result->success) {
            return $this->error($result->message);
        }

        return $this->success(['message' => $result->message]);
    }
}
