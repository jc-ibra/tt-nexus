<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class ProvisioningSystems extends BaseController
{
    public function index(): string
    {
        $svc = service('provisioningSystemAdmin');
        return view('App\Modules\Provisioning\Views\systems\index', [
            'pageTitle' => 'Sistemas destino',
            'systems'   => $svc->list(),
        ]);
    }

    public function show(int $id): string
    {
        $svc    = service('provisioningSystemAdmin');
        $system = $svc->find($id);
        if (! $system) {
            throw PageNotFoundException::forPageNotFound();
        }

        $data = [
            'pageTitle' => 'Sistema: ' . $system['name'],
            'system'    => $system,
            'expected'  => $svc->expectedCredentials($system['key']),
        ];

        // GLPI-specific tools: DB connection config + additional-fields catalogs.
        if ($system['key'] === 'glpi') {
            $settings              = service('provisioningSettings')->getAll();
            $data['glpiSettings']  = $settings;
            $data['glpiHasPassword'] = ($settings['glpi_db_password'] ?? '') !== '';
            $data['glpiConfigured']  = service('glpiDbConnection')->isConfigured();
        }

        return view('App\Modules\Provisioning\Views\systems\show', $data);
    }

    public function edit(int $id): string
    {
        $svc    = service('provisioningSystemAdmin');
        $system = $svc->find($id);
        if (! $system) {
            throw PageNotFoundException::forPageNotFound();
        }
        return view('App\Modules\Provisioning\Views\systems\form', [
            'pageTitle' => 'Editar sistema: ' . $system['name'],
            'system'    => $system,
            'expected'  => $svc->expectedCredentials($system['key']),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc = service('provisioningSystemAdmin');

        $data = [
            'base_url'    => $this->request->getPost('base_url'),
            'description' => $this->request->getPost('description'),
            'notes'       => $this->request->getPost('notes'),
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
            'options'     => $this->request->getPost('options') ?? [],
        ];
        $creds  = $this->request->getPost('credentials') ?? [];

        $result = $svc->update($id, $data, is_array($creds) ? $creds : []);

        if (! $result->success) {
            session()->setFlashdata('error', $result->message);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('provisioning.systems.show', $id));
    }

    public function test(int $id): ResponseInterface
    {
        $orch   = service('provisioningOrchestrator');
        $result = $orch->testSystem($id);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('provisioning.systems.show', $id));
    }

    public function toggle(int $id): ResponseInterface
    {
        $svc    = service('provisioningSystemAdmin');
        $result = $svc->toggleActive($id);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('provisioning.systems.index'));
    }
}
