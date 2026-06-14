<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeAreas extends BaseController
{
    public function index(): string
    {
        $svc     = service('employeeCatalogService');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        return view('App\Modules\Employees\Views\employees\catalogs\areas_index', [
            'pageTitle' => 'Áreas',
            'areas'     => $svc->paginateAreas($perPage, $page),
            'total'     => $svc->totalAreas(),
            'page'      => $page,
            'perPage'   => $perPage,
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\areas_form', [
            'pageTitle' => 'Nueva área',
            'area'      => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->createArea($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.areas.index'));
    }

    public function edit(int $id): string
    {
        $svc  = service('employeeCatalogService');
        $area = $svc->findArea($id);

        if (! $area) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\catalogs\areas_form', [
            'pageTitle' => 'Editar área',
            'area'      => $area,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->updateArea($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.areas.index'));
    }

    public function destroy(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $result = $svc->destroyArea($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.areas.index'));
    }
}
