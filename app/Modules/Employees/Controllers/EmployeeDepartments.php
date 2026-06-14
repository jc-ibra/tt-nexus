<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeDepartments extends BaseController
{
    public function index(): string
    {
        $svc     = service('employeeCatalogService');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        return view('App\Modules\Employees\Views\employees\catalogs\departments_index', [
            'pageTitle'   => 'Departamentos',
            'departments' => $svc->paginateDepartments($perPage, $page),
            'total'       => $svc->totalDepartments(),
            'page'        => $page,
            'perPage'     => $perPage,
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\departments_form', [
            'pageTitle'  => 'Nuevo departamento',
            'department' => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->createDepartment($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.departments.index'));
    }

    public function edit(int $id): string
    {
        $svc        = service('employeeCatalogService');
        $department = $svc->findDepartment($id);

        if (! $department) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\catalogs\departments_form', [
            'pageTitle'  => 'Editar departamento',
            'department' => $department,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->updateDepartment($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.departments.index'));
    }

    public function destroy(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $result = $svc->destroyDepartment($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.departments.index'));
    }
}
