<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeStates extends BaseController
{
    public function index(): string
    {
        $svc     = service('employeeCatalogService');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        return view('App\Modules\Employees\Views\employees\catalogs\states_index', [
            'pageTitle' => 'Estados de origen',
            'states'    => $svc->paginateStates($perPage, $page),
            'total'     => $svc->totalStates(),
            'page'      => $page,
            'perPage'   => $perPage,
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\states_form', [
            'pageTitle' => 'Nuevo estado',
            'state'     => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->createState($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.states.index'));
    }

    public function edit(int $id): string
    {
        $svc   = service('employeeCatalogService');
        $state = $svc->findState($id);

        if (! $state) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\catalogs\states_form', [
            'pageTitle' => 'Editar estado',
            'state'     => $state,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->updateState($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.states.index'));
    }

    public function destroy(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $result = $svc->destroyState($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.states.index'));
    }
}
