<?php

namespace App\Modules\Communications\Controllers;

use App\Controllers\BaseController;

class Communications extends BaseController
{
    public function index(): string
    {
        $svc   = service('communicationService');
        $page  = (int) ($this->request->getGet('page') ?? 1);
        $perPage = 20;

        return view('App\Modules\Communications\Views\communications\index', [
            'pageTitle'      => 'Comunicados',
            'communications' => $svc->paginate($perPage),
            'total'          => $svc->total(),
            'page'           => $page,
            'perPage'        => $perPage,
        ]);
    }

    public function compose(): string
    {
        $svc = service('communicationService');

        return view('App\Modules\Communications\Views\communications\form', [
            'pageTitle'     => 'Nuevo comunicado',
            'communication' => null,
            'allLists'      => $svc->getListsForSelect(),
        ]);
    }

    public function store()
    {
        $svc  = service('communicationService');
        $data = $this->request->getPost(['subject', 'body', 'from_name', 'from_email', 'list_ids', 'priority', 'request_read_receipt']);

        $result = $svc->create($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('comms.show', $result->data['id']));
    }

    public function show(int $id): string
    {
        $svc  = service('communicationService');
        $comm = $svc->findById($id);

        if (! $comm) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Communications\Views\communications\show', [
            'pageTitle'     => $comm['subject'],
            'communication' => $comm,
        ]);
    }

    public function edit(int $id): string
    {
        $svc  = service('communicationService');
        $comm = $svc->findById($id);

        if (! $comm) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Communications\Views\communications\form', [
            'pageTitle'     => 'Editar comunicado',
            'communication' => $comm,
            'allLists'      => $svc->getListsForSelect(),
        ]);
    }

    public function update(int $id)
    {
        $svc  = service('communicationService');
        $data = $this->request->getPost(['subject', 'body', 'from_name', 'from_email', 'list_ids', 'priority', 'request_read_receipt']);

        $result = $svc->update($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', is_array($result->errors) ? $result->errors : ['error' => $result->errors]);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('comms.show', $id));
    }

    public function destroy(int $id)
    {
        $svc    = service('communicationService');
        $result = $svc->destroy($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_string($result->errors) ? $result->errors : implode(' ', $result->errors));
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('comms.index'));
    }

    public function preview(int $id): string
    {
        $svc    = service('communicationService');
        $result = $svc->renderPreview($id);

        if (! $result->success) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Communications\Views\communications\preview', [
            'pageTitle' => 'Vista previa',
            'html'      => $result->data['html'],
            'id'        => $id,
        ]);
    }

    public function sendTest(int $id)
    {
        $svc   = service('communicationService');
        $email = $this->request->getPost('email');
        $comm  = $svc->findById($id);

        if (! $comm) {
            session()->setFlashdata('error', 'Comunicado no encontrado.');
            return redirect()->to(route_to('comms.index'));
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session()->setFlashdata('error', 'Correo no válido.');
            return redirect()->to(route_to('comms.show', $id));
        }

        $mailerSvc = new \App\Modules\Communications\Services\MailerService();
        $result    = $mailerSvc->sendSingle(
            $email,
            $email,
            $comm['from_email'],
            $comm['from_name'],
            '[TEST] ' . $comm['subject'],
            $svc->buildEmailHtml($comm['subject'], $comm['body'])
        );

        if ($result['success']) {
            session()->setFlashdata('success', 'Correo de prueba enviado a ' . $email);
        } else {
            log_message('error', '[comms.sendTest] ' . $result['error']);
            session()->setFlashdata('error', 'No se pudo enviar el correo de prueba. Verifica que el servidor SMTP este configurado correctamente o contacta a tu administrador.');
        }

        return redirect()->to(route_to('comms.show', $id));
    }

    public function send(int $id)
    {
        $svc    = service('communicationService');
        $result = $svc->queueForSending($id);

        if (! $result->success) {
            $msg = is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors;
            session()->setFlashdata('error', $msg);
        } else {
            session()->setFlashdata('success', $result->message . ' Ejecuta el proceso de cola para enviar.');
        }

        return redirect()->to(route_to('comms.show', $id));
    }

    public function logs(int $id): string
    {
        $svc  = service('communicationService');
        $comm = $svc->findById($id);

        if (! $comm) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $logData = $svc->getLogs($id);

        return view('App\Modules\Communications\Views\communications\logs', [
            'pageTitle'     => 'Registros — ' . $comm['subject'],
            'communication' => $comm,
            'logs'          => $logData['logs'],
            'stats'         => $logData['stats'],
            'pager'         => $logData['pager'],
        ]);
    }
}
