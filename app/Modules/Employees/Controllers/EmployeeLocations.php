<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeLocations extends BaseController
{
    public function index(): string
    {
        $svc     = service('employeeCatalogService');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        return view('App\Modules\Employees\Views\employees\catalogs\locations_index', [
            'pageTitle' => 'Ubicaciones de origen',
            'locations' => $svc->paginateLocations($perPage, $page),
            'total'     => $svc->totalLocations(),
            'page'      => $page,
            'perPage'   => $perPage,
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\locations_form', [
            'pageTitle' => 'Nueva ubicación',
            'location'  => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->createLocation($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.locations.index'));
    }

    public function edit(int $id): string
    {
        $svc      = service('employeeCatalogService');
        $location = $svc->findLocation($id);

        if (! $location) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\catalogs\locations_form', [
            'pageTitle' => 'Editar ubicación',
            'location'  => $location,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $data   = $this->request->getPost(['name', 'status']);
        $result = $svc->updateLocation($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.locations.index'));
    }

    public function destroy(int $id): ResponseInterface
    {
        $svc    = service('employeeCatalogService');
        $result = $svc->destroyLocation($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.locations.index'));
    }

    public function importForm(): string
    {
        return view('App\Modules\Employees\Views\employees\catalogs\import', [
            'pageTitle' => 'Importar ubicaciones de origen',
            'backRoute' => route_to('employees.locations.index'),
            'postRoute' => route_to('employees.locations.import.post'),
            'sample'    => "name\nGuadalajara\nMonterrey\nCDMX",
        ]);
    }

    public function import(): ResponseInterface
    {
        $file = $this->request->getFile('file');

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            session()->setFlashdata('error', 'Selecciona un archivo CSV.');
            return redirect()->back();
        }

        $result = service('employeeCatalogService')->importLocations($file);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
            return redirect()->back();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.locations.index'));
    }
}
