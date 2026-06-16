<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class ProvisioningMsLicenses extends BaseController
{
    private function svc(): \App\Modules\Provisioning\Services\MsLicenseService
    {
        return service('msLicenseService');
    }

    public function index(): string
    {
        return view('App\Modules\Provisioning\Views\ms_licenses\index', [
            'pageTitle' => 'Licencias Microsoft 365',
            'licenses'  => $this->svc()->list(),
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\Provisioning\Views\ms_licenses\form', [
            'pageTitle' => 'Nueva licencia M365',
            'license'   => null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $result = $this->svc()->create($this->request->getPost());

        if (! $result->success) {
            session()->setFlashdata('error', $result->message);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('provisioning.ms-licenses.index'));
    }

    public function edit(int $id): string
    {
        $license = $this->svc()->find($id);
        if (! $license) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Provisioning\Views\ms_licenses\form', [
            'pageTitle' => 'Editar licencia: ' . $license['name'],
            'license'   => $license,
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $result = $this->svc()->update($id, $this->request->getPost());

        if (! $result->success) {
            session()->setFlashdata('error', $result->message);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('provisioning.ms-licenses.index'));
    }

    public function destroy(int $id): ResponseInterface
    {
        $result = $this->svc()->destroy($id);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('provisioning.ms-licenses.index'));
    }

    public function toggle(int $id): ResponseInterface
    {
        $result = $this->svc()->toggleActive($id);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('provisioning.ms-licenses.index'));
    }
}
