<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class Employees extends BaseController
{
    public function index(): string
    {
        $svc        = service('employeeService');
        $catalogSvc = service('employeeCatalogService');

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        $filters = [
            'q'             => trim((string) ($this->request->getGet('q') ?? '')),
            'area_id'       => $this->request->getGet('area_id'),
            'department_id' => $this->request->getGet('department_id'),
            'position_id'   => $this->request->getGet('position_id'),
            'active'        => $this->request->getGet('active'),
        ];

        return view('App\Modules\Employees\Views\employees\index', [
            'pageTitle'   => 'Empleados',
            'employees'   => $svc->paginate($filters, $perPage, $page),
            'total'       => $svc->total($filters),
            'page'        => $page,
            'perPage'     => $perPage,
            'filters'     => $filters,
            'areas'       => $catalogSvc->listActiveAreas(),
            'departments' => $catalogSvc->listActiveDepartments(),
            'positions'   => $catalogSvc->listActivePositions(),
        ]);
    }

    public function new(): string
    {
        $catalogSvc = service('employeeCatalogService');

        return view('App\Modules\Employees\Views\employees\form', [
            'pageTitle'   => 'Nuevo empleado',
            'employee'    => null,
            'areas'       => $catalogSvc->listActiveAreas(),
            'departments' => $catalogSvc->listActiveDepartments(),
            'positions'   => $catalogSvc->listActivePositions(),
            'states'      => $catalogSvc->listActiveStates(),
            'locations'   => $catalogSvc->listActiveLocations(),
        ]);
    }

    public function store(): ResponseInterface
    {
        $svc  = service('employeeService');
        $data = $this->collectFormData();

        $result = $svc->create($data);

        if (! $result->success) {
            session()->setFlashdata('errors', $result->errors);
            return redirect()->back()->withInput();
        }

        // Optional photo uploaded together with the create form.
        $file = $this->request->getFile('photo');
        if ($file && $file->isValid()) {
            $photoResult = $svc->saveUploadedPhoto($result->data['id'], $file);
            if (! $photoResult->success) {
                $err = is_array($photoResult->errors) ? implode(' ', $photoResult->errors) : (string) $photoResult->errors;
                session()->setFlashdata('warning', 'Empleado creado, pero la foto no se guardó: ' . $err);
            }
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.show', $result->data['id']));
    }

    public function show(int $id): string
    {
        $svc      = service('employeeService');
        $employee = $svc->findById($id);

        if (! $employee) {
            throw PageNotFoundException::forPageNotFound();
        }

        $msLicenses = (new \App\Modules\Provisioning\Models\MsLicenseModel())->getAllActive();

        return view('App\Modules\Employees\Views\employees\show', [
            'pageTitle'     => trim($employee['name'] . ' ' . ($employee['lastname'] ?? '')),
            'employee'      => $employee,
            'reports'       => $svc->directReports($id),
            'emailAccounts' => $svc->listEmailAccounts($id),
            'msLicenses'    => $msLicenses,
            'hasEmail'      => $svc->hasConfiguredEmail($id),
        ]);
    }

    public function edit(int $id): string
    {
        $svc        = service('employeeService');
        $catalogSvc = service('employeeCatalogService');
        $employee   = $svc->findById($id);

        if (! $employee) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Employees\Views\employees\form', [
            'pageTitle'   => 'Editar empleado',
            'employee'    => $employee,
            'areas'       => $catalogSvc->listActiveAreas(),
            'departments' => $catalogSvc->listActiveDepartments(),
            'positions'   => $catalogSvc->listActivePositions(),
            'states'      => $catalogSvc->listActiveStates(),
            'locations'   => $catalogSvc->listActiveLocations(),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc  = service('employeeService');
        $data = $this->collectFormData();

        $result = $svc->update($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', is_array($result->errors) ? $result->errors : ['error' => $result->errors]);
            return redirect()->back()->withInput();
        }

        // Optional photo uploaded together with the edit form.
        $file = $this->request->getFile('photo');
        if ($file && $file->isValid()) {
            $photoResult = $svc->saveUploadedPhoto($id, $file);
            if (! $photoResult->success) {
                $err = is_array($photoResult->errors) ? implode(' ', $photoResult->errors) : (string) $photoResult->errors;
                session()->setFlashdata('warning', 'Empleado actualizado, pero la foto no se guardó: ' . $err);
            }
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('employees.show', $id));
    }

    public function destroy(int $id): ResponseInterface
    {
        $svc    = service('employeeService');
        $result = $svc->destroy($id);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.index'));
    }

    public function uploadPhoto(int $id): ResponseInterface
    {
        $svc  = service('employeeService');
        $file = $this->request->getFile('photo');

        if (! $file) {
            session()->setFlashdata('error', 'No se recibió ningún archivo.');
            return redirect()->to(route_to('employees.edit', $id));
        }

        $result = $svc->saveUploadedPhoto($id, $file);

        if (! $result->success) {
            session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.edit', $id));
    }

    public function servePhoto(int $id): ResponseInterface
    {
        $svc  = service('employeeService');
        $path = $svc->getPhotoPath($id);

        if (! $path) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', mime_content_type($path) ?: 'application/octet-stream')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody(file_get_contents($path));
    }

    /**
     * Same-origin proxy that the create/edit form uses to populate the email
     * autocomplete dropdown with Mailcow mailboxes. Keeps the Bearer token off
     * the page.
     */
    public function mailboxesSearch(): ResponseInterface
    {
        $svc   = service('employeeService');
        $term  = trim((string) ($this->request->getGet('q') ?? ''));
        $limit = (int) ($this->request->getGet('limit') ?? 20);

        $matches = $svc->searchMailboxes($term, $limit > 0 && $limit <= 50 ? $limit : 20);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $matches,
        ]);
    }

    /**
     * Same-origin proxy for the "jefe directo" autocomplete in the form.
     */
    public function searchEmployees(): ResponseInterface
    {
        $svc       = service('employeeService');
        $term      = trim((string) ($this->request->getGet('q') ?? ''));
        $limit     = (int) ($this->request->getGet('limit') ?? 10);
        $excludeId = $this->request->getGet('exclude_id') !== null
            ? (int) $this->request->getGet('exclude_id')
            : null;

        $matches = $term === ''
            ? []
            : $svc->search($term, $limit > 0 && $limit <= 50 ? $limit : 10, $excludeId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $matches,
        ]);
    }

    public function addEmailAccount(int $employeeId): ResponseInterface
    {
        $svc    = service('employeeService');
        $result = $svc->addEmailAccount($employeeId, $this->request->getPost());

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function removeEmailAccount(int $employeeId, int $accountId): ResponseInterface
    {
        $svc    = service('employeeService');
        $result = $svc->removeEmailAccount($accountId, $employeeId);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function linkMailbox(int $id): ResponseInterface
    {
        $svc          = service('employeeService');
        $mailboxEmail = trim((string) ($this->request->getPost('mailbox_email') ?? ''));

        if (! filter_var($mailboxEmail, FILTER_VALIDATE_EMAIL)) {
            session()->setFlashdata('error', 'Selecciona un buzón válido de Mailcow.');
            return redirect()->to(route_to('employees.show', $id));
        }

        $result = $svc->linkMailbox($id, $mailboxEmail);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);

        return redirect()->to(route_to('employees.show', $id));
    }

    public function unlinkMailbox(int $id): ResponseInterface
    {
        $svc    = service('employeeService');
        $result = $svc->unlinkMailbox($id);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', is_array($result->errors) ? implode(' ', $result->errors) : (string) $result->errors);

        return redirect()->to(route_to('employees.show', $id));
    }

    private function collectFormData(): array
    {
        // Note: email_secondary, telephone, ext and has_mailbox are intentionally
        // omitted. They are no longer part of the form, and pulling them in as
        // null would wipe existing values (or reset has_mailbox) on update.
        return $this->request->getPost([
            'employee_number', 'name', 'lastname', 'email',
            'cellphone',
            'position_id', 'department_id', 'area_id', 'state_id', 'location_id', 'parent_id',
            'date_entry', 'date_discharge',
            'active',
        ]);
    }
}
