<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeDocumentTypes extends BaseController
{
    public function index(): string
    {
        $svc = service('employeeDocumentService');

        return view('App\Modules\Employees\Views\employees\catalogs\document_types_index', [
            'pageTitle' => 'Tipos de documento',
            'types'     => $svc->listTypes(false),
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\document_types_form', [
            'pageTitle' => 'Nuevo tipo de documento',
            'type'      => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc    = service('employeeDocumentService');
        $result = $svc->createType($this->request->getPost([
            'key', 'name', 'description', 'allowed_mimes', 'max_size_kb', 'is_active', 'sort_order',
        ]) ?? []);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.document-types.index'));
    }

    public function edit(int $id): string
    {
        $type = service('employeeDocumentService')->findType($id);
        if (! $type) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\catalogs\document_types_form', [
            'pageTitle' => 'Editar tipo de documento',
            'type'      => $type,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc    = service('employeeDocumentService');
        $result = $svc->updateType($id, $this->request->getPost([
            'name', 'description', 'allowed_mimes', 'max_size_kb', 'is_active', 'sort_order',
        ]) ?? []);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.document-types.index'));
    }
}
