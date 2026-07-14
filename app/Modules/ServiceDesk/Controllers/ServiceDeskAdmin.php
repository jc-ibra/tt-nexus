<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use App\Modules\ServiceDesk\Models\ServiceDeskAiUsageModel;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Services\ServiceDeskSettings;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SuperAdmin configuration for Service Desk: import ceilings, GLPI targets,
 * which plugin containers operators may import into, and catalog autocreation.
 */
class ServiceDeskAdmin extends BaseController
{
    public function settings(): string
    {
        $introspector = service('glpiSchemaIntrospector');
        $configured   = $introspector->isConfigured();
        $settings     = service('serviceDeskSettings');

        return view('App\Modules\ServiceDesk\Views\settings', [
            'pageTitle'      => 'Configuración · Service Desk',
            'configured'     => $configured,
            'settings'       => $settings->all(),
            'containers'     => $configured ? $introspector->containerOptions() : [],
            'aiModels'       => ServiceDeskSettings::AI_MODELS,
            'aiHasKey'       => $settings->aiHasApiKey(),
            'aiInstructions' => $settings->aiSystemPrompt(),
            'aiUsage'        => (new ServiceDeskAiUsageModel())->summary(30),
        ]);
    }

    public function saveSettings(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->save($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Saves the AI creator configuration (Claude API key, model, limits).
     */
    public function saveAi(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->saveAi($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings') . '#ai')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Category mapping screen: mark which GLPI categories are valid in the
     * template and set the CLIENTE used to homologate the ticket title.
     */
    public function categories(): string
    {
        $introspector = service('glpiSchemaIntrospector');
        $configured   = $introspector->isConfigured();

        return view('App\Modules\ServiceDesk\Views\categories', [
            'pageTitle'  => 'Categorías · Service Desk',
            'configured' => $configured,
            'categories' => $configured ? $introspector->categories() : [],
            'map'        => (new ServiceDeskCategoryMapModel())->all(),
        ]);
    }

    public function saveCategories(): ResponseInterface
    {
        $categories = service('glpiSchemaIntrospector')->categories();
        $clientes   = (array) ($this->request->getPost('cliente') ?? []);
        $supported  = (array) ($this->request->getPost('supported') ?? []);

        $rows = [];
        foreach ($categories as $c) {
            $id = (int) $c['id'];
            $rows[$id] = [
                'category_name' => $c['name'],
                'cliente'       => trim((string) ($clientes[$id] ?? '')),
                'is_supported'  => isset($supported[$id]),
            ];
        }

        (new ServiceDeskCategoryMapModel())->saveAll($rows);

        return redirect()->to(route_to('servicedesk.categories'))
            ->with('success', 'Mapeo de categorías guardado.');
    }

    /**
     * JSON preview of the live introspected schema for one container.
     */
    public function schema(): ResponseInterface
    {
        $introspector = service('glpiSchemaIntrospector');
        if (! $introspector->isConfigured()) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'GLPI no está configurado.']);
        }

        $cid = (int) ($this->request->getGet('container') ?? 0);
        if ($cid <= 0) {
            return $this->response->setJSON(['status' => 'success', 'data' => $introspector->containerOptions()]);
        }

        $plan = $introspector->buildPlan([$cid], false);
        return $this->response->setJSON(['status' => 'success', 'data' => $plan['columns']]);
    }
}
