<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\HelpdeskSupervisorSettingsModel;
use App\Modules\HelpdeskSupervisor\Services\AuditRunnerService;
use App\Modules\HelpdeskSupervisor\Services\GlpiOverviewService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Settings extends BaseController
{
    public function index(): string
    {
        $settings = service('helpdeskSupervisorSettings');
        $overview = service('helpdeskGlpiOverview');

        $containers = [];
        try {
            $containers = service('glpiSchemaIntrospector')->containerOptions();
        } catch (\Throwable $e) {
            log_message('warning', '[HelpdeskSupervisor] could not load GLPI containers: ' . $e->getMessage());
        }

        return view('App\Modules\HelpdeskSupervisor\Views\settings', [
            'pageTitle'       => 'Configuración · Supervisor de Mesa',
            'all'             => $settings->all(),
            'containers'      => $containers,
            'tabKeys'         => AuditRunnerService::TAB_KEYS,
            'aiModels'        => \App\Modules\ServiceDesk\Services\ServiceDeskSettings::AI_MODELS,
            'entities'        => $overview->listEntities(),
            'rootCategories'  => $overview->listRootCategories(),
            'statusLabels'    => GlpiOverviewService::STATUS_LABELS,
            'typeLabels'      => GlpiOverviewService::TYPE_LABELS,
            'openStatuses'    => $settings->overviewOpenStatuses(),
            'ticketTypes'     => $settings->overviewTicketTypes(),
            'categoryRoots'   => $settings->overviewCategoryRoots(),
        ]);
    }

    public function save(): RedirectResponse
    {
        $input  = $this->request->getPost();
        $result = service('helpdeskSupervisorSettings')->save($input);

        $model = new HelpdeskSupervisorSettingsModel();
        $map   = [];
        foreach (AuditRunnerService::TAB_KEYS as $tab) {
            $key = 'tab_container_' . $tab;
            if (! array_key_exists($key, $input)) {
                continue; // connection-only save must not wipe the plugin tab map
            }
            $map[$key] = (string) max(0, (int) ($input[$key] ?? 0));
        }
        if ($map !== []) {
            $model->setMany($map);
        }

        $hash = trim((string) ($input['_settings_tab'] ?? 'connection'));
        if (! in_array($hash, ['connection', 'audit', 'overview', 'notifications'], true)) {
            $hash = 'connection';
        }

        if (! $result->success) {
            return redirect()->to(route_to('helpdesk.settings') . '#' . $hash)
                ->withInput()->with('error', $result->message);
        }
        return redirect()->to(route_to('helpdesk.settings') . '#' . $hash)
            ->with('success', $result->message);
    }

    public function saveOverview(): RedirectResponse
    {
        $result = service('helpdeskSupervisorSettings')->saveOverview($this->request->getPost());
        return redirect()->to(route_to('helpdesk.settings') . '#overview')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function saveNotifications(): RedirectResponse
    {
        $result = service('helpdeskSupervisorSettings')->saveNotifications($this->request->getPost());
        return redirect()->to(route_to('helpdesk.settings') . '#notifications')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function testConnection(): ResponseInterface
    {
        $reuse  = service('helpdeskSupervisorSettings')->reuseProvisioningConnection();
        $result = service('glpiDbConnection')->test();

        return $this->response->setJSON([
            'status'  => $result->success ? 'success' : 'error',
            'message' => $result->message,
            'reuse'   => $reuse,
        ]);
    }
}
