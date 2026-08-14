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

        $employees = $svc->paginate($filters, $perPage, $page);

        // The "Accesos" column (systems where the employee has an account) is
        // only relevant — and only visible — to the Provisioning role. Fetch the
        // accounts for the current page in a single query to avoid an N+1.
        $canProvision = service('access')->canAccessModule('provisioning');
        $provisioning = [];
        if ($canProvision && $employees !== []) {
            $provisioning = (new \App\Modules\Provisioning\Models\ProvisioningExternalAccountModel())
                ->mapForEmployees(array_column($employees, 'id'));
        }

        return view('App\Modules\Employees\Views\employees\index', [
            'pageTitle'    => 'Empleados',
            'employees'    => $employees,
            'total'        => $svc->total($filters),
            'stats'        => $svc->stats($filters),
            'page'         => $page,
            'perPage'      => $perPage,
            'filters'      => $filters,
            'areas'        => $catalogSvc->listActiveAreas(),
            'departments'  => $catalogSvc->listActiveDepartments(),
            'positions'    => $catalogSvc->listActivePositions(),
            'canProvision' => $canProvision,
            'provisioning' => $provisioning,
        ]);
    }

    /**
     * Downloads the directory as CSV or Excel, honouring the filters currently
     * applied on the index. Only the columns visible on that table are exported
     * (no photo, no personal contact data) — see EmployeeExportService.
     */
    public function export(): ResponseInterface
    {
        $exportSvc = service('employeeExportService');

        $filters = [
            'q'             => trim((string) ($this->request->getGet('q') ?? '')),
            'area_id'       => $this->request->getGet('area_id'),
            'department_id' => $this->request->getGet('department_id'),
            'position_id'   => $this->request->getGet('position_id'),
            'active'        => $this->request->getGet('active'),
        ];

        $format = strtolower(trim((string) ($this->request->getGet('format') ?? 'csv')));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $rows = $exportSvc->rows($filters);

        // The "Accesos" column only exists on screen for the Provisioning role;
        // the export must not widen what the user can already see.
        $canProvision = service('access')->canAccessModule('provisioning');
        $provisioning = [];
        if ($canProvision && $rows !== []) {
            $provisioning = (new \App\Modules\Provisioning\Models\ProvisioningExternalAccountModel())
                ->mapForEmployees(array_column($rows, 'id'));
        }

        $filename = 'empleados_' . date('Y-m-d_His');

        if ($format === 'xlsx') {
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"')
                ->setBody($exportSvc->toXlsx($rows, $provisioning, $canProvision));
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $exportSvc->toCsv($rows, $provisioning, $canProvision)); // UTF-8 BOM for Excel
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
            $photoResult = $svc->saveUploadedPhoto((int) $result->data['id'], $file);
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

        $msLicenses    = (new \App\Modules\Provisioning\Models\MsLicenseModel())->getAllActive();
        $emailAccounts = $svc->listEmailAccounts($id);

        return view('App\Modules\Employees\Views\employees\show', [
            'pageTitle'     => trim($employee['name'] . ' ' . ($employee['lastname'] ?? '')),
            'employee'      => $employee,
            'reports'       => $svc->directReports($id),
            'emailAccounts' => $emailAccounts,
            'primaryEmail'  => $svc->primaryEmailOf($emailAccounts),
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

        // Capture the values that must propagate outward so we only sync on change.
        $before = $svc->findById($id);

        // The employee number is read-only: force the stored value so an edit
        // (or a tampered POST) can never change it.
        if ($before) {
            $data['employee_number'] = $before['employee_number'] ?? '';
        }

        $result = $svc->update($id, $data);

        if (! $result->success) {
            session()->setFlashdata('errors', is_array($result->errors) ? $result->errors : ['error' => $result->errors]);
            return redirect()->back()->withInput();
        }

        // Optional photo uploaded together with the edit form.
        $file        = $this->request->getFile('photo');
        $photoChanged = false;
        if ($file && $file->isValid()) {
            $photoChanged = true;
            $photoResult  = $svc->saveUploadedPhoto($id, $file);
            if (! $photoResult->success) {
                $photoChanged = false;
                $err = is_array($photoResult->errors) ? implode(' ', $photoResult->errors) : (string) $photoResult->errors;
                session()->setFlashdata('warning', 'Empleado actualizado, pero la foto no se guardó: ' . $err);
            }
        }

        // Propagate Nombre/Apellidos (and Foto) to the external systems where the
        // employee already has an account. Only when one of those changed.
        $after       = $svc->findById($id);
        $nameChanged = ($before['name'] ?? '') !== ($after['name'] ?? '');
        $lastChanged = ($before['lastname'] ?? '') !== ($after['lastname'] ?? '');
        if ($nameChanged || $lastChanged || $photoChanged) {
            try {
                service('provisioningOrchestrator')->syncEmployeeProfile($id);
            } catch (\Throwable $e) {
                log_message('error', '[Employees] Profile sync to systems failed: ' . $e->getMessage());
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

    /**
     * Managing email accounts belongs to the Provisioning role, not Employees.
     * Employees-module users may see the accounts (read-only) but not edit them.
     */
    private function guardProvisioning(): ?ResponseInterface
    {
        if (service('access')->canAccessModule('provisioning')) {
            return null;
        }

        return service('response')->setStatusCode(403)->setBody(
            view('App\Modules\Core\Views\errors\403')
        );
    }

    public function addEmailAccount(int $employeeId): ResponseInterface
    {
        if ($denied = $this->guardProvisioning()) {
            return $denied;
        }

        $svc    = service('employeeService');
        $result = $svc->addEmailAccount($employeeId, $this->request->getPost());

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function updateEmailAccount(int $employeeId, int $accountId): ResponseInterface
    {
        if ($denied = $this->guardProvisioning()) {
            return $denied;
        }

        $svc    = service('employeeService');
        $result = $svc->updateEmailAccount($accountId, $employeeId, $this->request->getPost());

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function removeEmailAccount(int $employeeId, int $accountId): ResponseInterface
    {
        if ($denied = $this->guardProvisioning()) {
            return $denied;
        }

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
