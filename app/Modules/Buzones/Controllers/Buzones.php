<?php

declare(strict_types=1);

namespace App\Modules\Buzones\Controllers;

use App\Controllers\BaseController;

class Buzones extends BaseController
{
    private function svc(): \App\Modules\Buzones\Services\BuzonesService
    {
        return service('buzonesService');
    }

    // -----------------------------------------------------------------------
    // Index — HTML shell; data loaded via AJAX
    // -----------------------------------------------------------------------

    public function index(): string
    {
        return view('App\Modules\Buzones\Views\buzones\index', [
            'pageTitle' => 'Buzones de correo',
        ]);
    }

    // -----------------------------------------------------------------------
    // AJAX — data endpoints (return JSON)
    // -----------------------------------------------------------------------

    public function getData(): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = $this->svc()->listMailboxes();

        if (! $result->success) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => $result->message,
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data'    => $result->data,
        ]);
    }

    public function getDomains(): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = $this->svc()->getDomains();

        return $this->response->setJSON([
            'success' => $result->success,
            'data'    => $result->data ?? [],
            'error'   => $result->success ? null : $result->message,
        ]);
    }

    // -----------------------------------------------------------------------
    // AJAX — mutations (return JSON)
    // -----------------------------------------------------------------------

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Método no permitido.']);
        }

        $data   = (array) ($this->request->getJSON(true) ?? $this->request->getPost());
        $result = $this->svc()->createMailbox($data);

        return $this->response->setJSON([
            'success' => $result->success,
            'message' => $result->message,
            'errors'  => $result->errors,
        ]);
    }

    public function edit(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Método no permitido.']);
        }

        $body  = (array) ($this->request->getJSON(true) ?? $this->request->getPost());
        $email = trim($body['email'] ?? '');

        if (empty($email)) {
            return $this->response->setJSON(['success' => false, 'error' => 'El email es requerido.']);
        }

        $result = $this->svc()->editMailbox($email, $body);

        return $this->response->setJSON([
            'success' => $result->success,
            'message' => $result->message,
            'errors'  => $result->errors,
        ]);
    }

    public function delete(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Método no permitido.']);
        }

        $body   = (array) ($this->request->getJSON(true) ?? $this->request->getPost());
        $emails = (array) ($body['emails'] ?? []);

        $result = $this->svc()->deleteMailboxes($emails);

        return $this->response->setJSON([
            'success' => $result->success,
            'message' => $result->message,
        ]);
    }

    public function toggle(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Método no permitido.']);
        }

        $body   = (array) ($this->request->getJSON(true) ?? $this->request->getPost());
        $email  = trim($body['email'] ?? '');
        $active = (bool) ($body['active'] ?? false);

        if (empty($email)) {
            return $this->response->setJSON(['success' => false, 'error' => 'El email es requerido.']);
        }

        $result = $this->svc()->toggleMailbox($email, $active);

        return $this->response->setJSON([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ]);
    }

    // -----------------------------------------------------------------------
    // CSV export
    // -----------------------------------------------------------------------

    public function export(): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = $this->svc()->listMailboxes();

        if (! $result->success) {
            session()->setFlashdata('error', $result->message);
            return redirect()->to(route_to('buzones.index'));
        }

        $csv      = $this->svc()->exportCsv($result->data);
        $filename = 'buzones_' . date('Y-m-d_His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $csv); // UTF-8 BOM for Excel
    }

    // -----------------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------------

    public function settings(): string
    {
        return view('App\Modules\Buzones\Views\buzones\settings', [
            'pageTitle' => 'Buzones — Configuración',
            'settings'  => $this->svc()->getSettings(),
        ]);
    }

    public function saveSettings(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! $this->request->is('post')) {
            return redirect()->to(route_to('buzones.settings'));
        }

        $data   = $this->request->getPost();
        $result = $this->svc()->saveSettings($data);

        if ($result->success) {
            session()->setFlashdata('success', $result->message);
        } else {
            session()->setFlashdata('error', $result->message);
        }

        return redirect()->to(route_to('buzones.settings'));
    }

    public function testConnection(): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = $this->svc()->testConnection();

        return $this->response->setJSON([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ]);
    }
}
