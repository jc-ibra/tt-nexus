<?php

declare(strict_types=1);

namespace App\Modules\Communications\Controllers;

use App\Controllers\BaseController;

class Recipients extends BaseController
{
    public function index(): string
    {
        $data = service('recipientService')->paginate(20);

        return view('App\Modules\Communications\Views\recipients\index', array_merge($data, [
            'pageTitle' => 'Destinatarios',
        ]));
    }

    public function new(): string
    {
        return view('App\Modules\Communications\Views\recipients\form', [
            'pageTitle'  => 'Nuevo destinatario',
            'recipient'  => null,
        ]);
    }

    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'email', 'area', 'status']);
        $result = service('recipientService')->create($data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('comms.recipients.index'))->with('success', 'Destinatario creado.');
    }

    public function edit(int $id): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $recipient = service('recipientService')->findById($id);

        if ($recipient === null) {
            return redirect()->to(route_to('comms.recipients.index'))->with('error', 'Destinatario no encontrado.');
        }

        return view('App\Modules\Communications\Views\recipients\form', [
            'pageTitle' => 'Editar destinatario',
            'recipient' => $recipient,
        ]);
    }

    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'email', 'area', 'status']);
        $result = service('recipientService')->update($id, $data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('comms.recipients.index'))->with('success', 'Destinatario actualizado.');
    }

    public function destroy(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('recipientService')->destroy($id);

        if (! $result->success) {
            return redirect()->back()->with('error', $result->message);
        }

        return redirect()->to(route_to('comms.recipients.index'))->with('success', 'Destinatario eliminado.');
    }

    public function importForm(): string
    {
        return view('App\Modules\Communications\Views\recipients\import', [
            'pageTitle' => 'Importar destinatarios',
        ]);
    }

    public function import(): \CodeIgniter\HTTP\RedirectResponse
    {
        $file   = $this->request->getFile('file');
        $listId = $this->request->getPost('list_id') ? (int) $this->request->getPost('list_id') : null;
        $result = service('recipientService')->importFromFile($file, $listId);

        if (! $result->success) {
            return redirect()->back()->with('error', $result->message);
        }

        $d = $result->data;

        return redirect()->to(route_to('comms.recipients.index'))
            ->with('success', "Importación completa: {$d['imported']} importados, {$d['skipped']} omitidos.")
            ->with('import_errors', $d['errors']);
    }
}
