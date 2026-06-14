<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeePositions extends BaseController
{
    public function index(): string
    {
        $svc     = service('employeeCatalogService');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        return view('App\Modules\Employees\Views\employees\catalogs\positions_index', [
            'pageTitle' => 'Puestos',
            'positions' => $svc->paginatePositions($perPage, $page),
            'total'     => $svc->totalPositions(),
            'page'      => $page,
            'perPage'   => $perPage,
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\positions_form', [
            'pageTitle' => 'Nuevo puesto',
            'position'  => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->createPosition($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.positions.index'));
    }

    public function edit(int $id): string
    {
        $svc      = service('employeeCatalogService');
        $position = $svc->findPosition($id);

        if (! $position) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\catalogs\positions_form', [
            'pageTitle' => 'Editar puesto',
            'position'  => $position,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->updatePosition($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.positions.index'));
    }

    public function destroy(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $result = $svc->destroyPosition($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.positions.index'));
    }
}
