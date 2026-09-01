<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use App\Modules\Core\Models\UserModel;
use App\Modules\ServiceDesk\Models\ServiceDeskAiUsageModel;
use App\Modules\ServiceDesk\Models\ServiceDeskAssignmentModel;
use App\Modules\ServiceDesk\Models\ServiceDeskBacklogAreaModel;
use App\Modules\ServiceDesk\Models\ServiceDeskBacklogRunModel;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Services\AssignmentMatrixImporter;
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
        $assignments  = new ServiceDeskAssignmentModel();
        $stored       = $settings->all();

        // GLPI is an EXTERNAL DB. If it is enabled but unreachable, the schema
        // introspection throws — but this config page is exactly where the admin
        // goes to fix/disable that connection, so it must not 500. Degrade to
        // empty pickers plus a banner instead of crashing.
        $containers   = [];
        $backlogRoots = [];
        $glpiError    = null;
        if ($configured) {
            try {
                $containers   = $introspector->containerOptions();
                $backlogRoots = $introspector->rootCategories();
            } catch (\Throwable $e) {
                $glpiError = 'No se pudo conectar a la base de datos de GLPI. '
                    . 'Verifica que el servidor de GLPI esté disponible y que los datos en «Aprovisionamiento → Sistemas» sean correctos. '
                    . 'Detalle: ' . $e->getMessage();
                log_message('error', '[ServiceDeskAdmin] GLPI DB unreachable on settings: ' . $e->getMessage());
            }
        }

        return view('App\Modules\ServiceDesk\Views\settings', [
            'pageTitle'      => 'Configuración · Service Desk',
            'configured'     => $configured,
            'glpiError'      => $glpiError,
            'settings'       => $stored,
            'containers'     => $containers,
            'aiModels'       => ServiceDeskSettings::AI_MODELS,
            'aiHasKey'       => $settings->aiHasApiKey(),
            'aiInstructions' => $settings->aiSystemPrompt(),
            'aiUsage'        => (new ServiceDeskAiUsageModel())->summary(30),
            'widgetSiteKey'  => $settings->widgetSiteKey(),
            'widgetPrompt'   => $settings->widgetSystemPrompt(),
            // Public self-service landing tab.
            'landingSiteKey' => $settings->landingSiteKey(),
            'landingReady'   => $settings->landingReady(),
            'hasSupportedCats' => (new ServiceDeskCategoryMapModel())->hasSupported(),
            // Backlog report tab.
            'backlogAreas'   => (new \App\Modules\ServiceDesk\Config\ServiceDesk())->backlogAreas,
            'backlogRoots'   => $backlogRoots,
            'backlogAreaMap' => (new ServiceDeskBacklogAreaModel())->all(),
            'backlogRuns'    => (new ServiceDeskBacklogRunModel())->recent(8),
            // Assignments tab: the stored matrix plus the roster to map to users.
            'assignAgents'    => $assignments->agents(),
            'assignCategories' => $assignments->categoryCount(),
            'assignCells'     => $assignments->cellCount(),
            'assignUpdatedAt' => $stored[AssignmentMatrixImporter::KEY_UPDATED] ?? '',
            'assignFilename'  => $stored[AssignmentMatrixImporter::KEY_FILENAME] ?? '',
            'assignUsers'     => (new UserModel())
                ->select('id, name, email')->where('status', 'active')->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    /**
     * Replaces the assignment matrix from an uploaded workbook.
     *
     * The file is parsed in full before anything is written, so a bad upload
     * leaves the matrix the agents are reading untouched.
     */
    public function saveAssignments(): ResponseInterface
    {
        $back = route_to('servicedesk.settings') . '#asignaciones';
        $file = $this->request->getFile('file');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to($back)->with('error', 'Selecciona un archivo .xlsx válido.');
        }
        if (! in_array(strtolower($file->getExtension()), ['xlsx', 'xlsm'], true)) {
            return redirect()->to($back)->with('error', 'El archivo debe ser .xlsx.');
        }

        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        $tmpName = 'assign_' . bin2hex(random_bytes(6)) . '.xlsx';
        $file->move($tmpDir, $tmpName);
        $tmpPath = $tmpDir . '/' . $tmpName;

        $result = service('assignmentMatrixImporter')->import(
            $tmpPath,
            $file->getClientName(),
            (int) session()->get('user_id')
        );

        // The workbook is only a transport: the matrix now lives in the database
        // and the file is never handed back to anyone, so it does not stay.
        @unlink($tmpPath);

        return redirect()->to($back)->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Maps each name in the sheet to a Nexus user, which is what powers the
     * agent's "solo lo mío" filter.
     */
    public function saveAssignmentAgents(): ResponseInterface
    {
        $map = [];
        foreach ((array) ($this->request->getPost('agent_user') ?? []) as $agentId => $userId) {
            $map[(int) $agentId] = (int) $userId;
        }

        (new ServiceDeskAssignmentModel())->saveAgentUsers($map);

        return redirect()->to(route_to('servicedesk.settings') . '#asignaciones')
            ->with('success', 'Personas de la matriz vinculadas.');
    }

    /**
     * Saves the backlog report configuration (sender, audience, cut-off hour,
     * thresholds, and the plugin field that marks "IDC").
     */
    public function saveBacklog(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->saveBacklog($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings') . '#backlog')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Saves the root ITIL category -> business area mapping for the report.
     */
    public function saveBacklogAreas(): ResponseInterface
    {
        $roots  = service('glpiSchemaIntrospector')->rootCategories();
        $areas  = (array) ($this->request->getPost('area') ?? []);
        $valid  = array_keys((new \App\Modules\ServiceDesk\Config\ServiceDesk())->backlogAreas);

        $rows = [];
        foreach ($roots as $c) {
            $id   = (int) $c['id'];
            $area = trim((string) ($areas[$id] ?? ''));
            $rows[$id] = [
                'area'          => in_array($area, $valid, true) ? $area : '',
                'category_name' => $c['name'],
            ];
        }
        (new ServiceDeskBacklogAreaModel())->saveAll($rows);

        return redirect()->to(route_to('servicedesk.settings') . '#backlog')
            ->with('success', 'Mapeo de áreas guardado.');
    }

    /**
     * Sends a one-off test report to a given address (or the configured To list).
     */
    public function sendBacklogTest(): ResponseInterface
    {
        $email = trim((string) $this->request->getPost('test_email'));
        $to    = $email !== '' ? [$email] : [];

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to(route_to('servicedesk.settings') . '#backlog')
                ->with('error', 'El correo de prueba no es válido.');
        }

        $userId = session()->get('user_id');
        $result = service('backlogReportService')->send('test', $userId ? (int) $userId : null, $to);

        return redirect()->to(route_to('servicedesk.settings') . '#backlog')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function saveSettings(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->save($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Saves the self-service widget configuration (enable, site key, allowed
     * origins, hardware container + field mapping, generic requester, limits).
     */
    public function saveWidget(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->saveWidget($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings') . '#widget')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Saves the AI creator configuration (Claude API key, model, limits).
     */
    /**
     * Bulk update + close block: enable switch, reopen/verify toggles and the
     * default solution text used when a row does not carry its own.
     */
    public function saveUpdate(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->saveUpdate($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings') . '#update')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function saveAi(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->saveAi($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings') . '#ai')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Saves the public self-service landing configuration (enable, title/intro,
     * key regeneration, rate limit). The landing lives at /soporte.
     */
    public function saveLanding(): ResponseInterface
    {
        $result = service('serviceDeskSettings')->saveLanding($this->request->getPost());
        return redirect()->to(route_to('servicedesk.settings') . '#widget')
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
            'pageTitle'       => 'Categorías · Service Desk',
            'configured'      => $configured,
            'categories'      => $configured ? $introspector->categories() : [],
            'map'             => (new ServiceDeskCategoryMapModel())->all(),
            'widgetCategoryId' => service('serviceDeskSettings')->widgetItilCategoryId(),
        ]);
    }

    public function saveCategories(): ResponseInterface
    {
        $categories = service('glpiSchemaIntrospector')->categories();
        $clientes   = (array) ($this->request->getPost('cliente') ?? []);
        $supported  = (array) ($this->request->getPost('supported') ?? []);
        $regional   = (array) ($this->request->getPost('backlog_regional') ?? []);
        $idcScope   = (array) ($this->request->getPost('backlog_idc') ?? []);
        $cliScope   = (array) ($this->request->getPost('backlog_cliente') ?? []);
        $idsTabScope = (array) ($this->request->getPost('audit_ids_tab') ?? []);

        $rows = [];
        foreach ($categories as $c) {
            $id = (int) $c['id'];
            $rows[$id] = [
                'category_name'    => $c['name'],
                'cliente'          => trim((string) ($clientes[$id] ?? '')),
                'is_supported'     => isset($supported[$id]),
                'backlog_regional' => isset($regional[$id]),
                'backlog_idc'      => isset($idcScope[$id]),
                'backlog_cliente'  => isset($cliScope[$id]),
                'audit_ids_tab'    => isset($idsTabScope[$id]),
            ];
        }

        (new ServiceDeskCategoryMapModel())->saveAll($rows);

        // The single ITIL category the self-service widget files tickets under.
        service('serviceDeskSettings')->setWidgetCategory((int) ($this->request->getPost('widget_category') ?? 0));

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
