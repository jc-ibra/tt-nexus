<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\HelpdeskSupervisorSettingsModel;
use App\Modules\HelpdeskSupervisor\Services\AuditRunnerService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Settings extends BaseController
{
    public function index(): string
    {
        $settings = service('helpdeskSupervisorSettings');

        // Container options from the live GLPI plugin schema (for tab mapping).
        $containers = [];
        try {
            $containers = service('glpiSchemaIntrospector')->containerOptions();
        } catch (\Throwable $e) {
            log_message('warning', '[HelpdeskSupervisor] could not load GLPI containers: ' . $e->getMessage());
        }

        return view('App\Modules\HelpdeskSupervisor\Views\settings', [
            'pageTitle'  => 'Configuración · Supervisor de Mesa',
            'all'        => $settings->all(),
            'containers' => $containers,
            'tabKeys'    => AuditRunnerService::TAB_KEYS,
        ]);
    }

    public function save(): RedirectResponse
    {
        $input  = $this->request->getPost();
        $result = service('helpdeskSupervisorSettings')->save($input);

        // Persist the tab -> container mapping (rules use these to read plugin data).
        $model = new HelpdeskSupervisorSettingsModel();
        $map   = [];
        foreach (AuditRunnerService::TAB_KEYS as $tab) {
            $map['tab_container_' . $tab] = (string) max(0, (int) ($input['tab_container_' . $tab] ?? 0));
        }
        $model->setMany($map);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('error', $result->message);
        }
        return redirect()->to(route_to('helpdesk.settings'))->with('success', $result->message);
    }

    /** AJAX: tests the GLPI connection the audit will use. */
    public function testConnection(): ResponseInterface
    {
        $reuse = service('helpdeskSupervisorSettings')->reuseProvisioningConnection();

        // Same instance as Provisioning by default: test the shared connection.
        // (Own-connection testing is reserved for a future separate GLPI target.)
        $result = service('glpiDbConnection')->test();

        return $this->response->setJSON([
            'status'  => $result->success ? 'success' : 'error',
            'message' => $result->message,
            'reuse'   => $reuse,
        ]);
    }
}
