<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Operator-facing bulk import. Download a template for a container selection,
 * upload the filled Excel, and monitor the async import.
 */
class ServiceDesk extends BaseController
{
    private function introspector()
    {
        return service('glpiSchemaIntrospector');
    }

    private function settings()
    {
        return service('serviceDeskSettings');
    }

    public function index(): string
    {
        $settings = $this->settings();

        if (! $this->introspector()->isConfigured()) {
            return view('App\Modules\ServiceDesk\Views\index', [
                'pageTitle'  => 'Service Desk',
                'configured' => false,
                'containers' => [],
                'settings'   => $settings,
                'imports'    => [],
            ]);
        }

        $containers = $this->introspector()->containerOptions($settings->includedContainerIds());
        $imports    = (new ServiceDeskImportModel())->recent(10);

        return view('App\Modules\ServiceDesk\Views\index', [
            'pageTitle'  => 'Service Desk',
            'configured' => true,
            'containers' => $containers,
            'settings'   => $settings,
            'imports'    => $imports,
        ]);
    }

    /**
     * Streams a freshly generated template for the selected containers.
     */
    public function downloadTemplate(): ResponseInterface
    {
        $ids = $this->requestedContainers();
        if ($ids === []) {
            return redirect()->to(route_to('servicedesk.index'))
                ->with('error', 'Selecciona al menos un tipo de ticket (contenedor) para el template.');
        }

        $path = WRITEPATH . 'servicedesk/tmp/template_' . implode('-', $ids) . '_' . bin2hex(random_bytes(4)) . '.xlsx';
        $this->ensureDir(dirname($path));

        try {
            service('ticketTemplateBuilder')->build($ids, $path);
        } catch (\Throwable $e) {
            log_message('error', '[ServiceDesk] template build failed: ' . $e->getMessage());
            return redirect()->to(route_to('servicedesk.index'))
                ->with('error', 'No se pudo generar el template: ' . $e->getMessage());
        }

        $filename = 'template_service_desk_' . date('Ymd_His') . '.xlsx';
        return $this->response->download($path, null)->setFileName($filename);
    }

    /**
     * Receives the filled Excel, validates it, and enqueues the import.
     */
    public function upload(): ResponseInterface
    {
        if (! $this->settings()->importEnabled()) {
            return redirect()->to(route_to('servicedesk.index'))
                ->with('error', 'La importación está deshabilitada por el administrador.');
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->withInput()
                ->with('error', 'Indica un nombre para la carga (ej. el lote o periodo que importas).');
        }

        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->to(route_to('servicedesk.index'))
                ->with('error', 'Selecciona un archivo .xlsx válido.');
        }
        if (strtolower($file->getExtension()) !== 'xlsx') {
            return redirect()->to(route_to('servicedesk.index'))
                ->with('error', 'El archivo debe ser .xlsx (el template descargado).');
        }

        // Stage the upload in a temp path for validation.
        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        $this->ensureDir($tmpDir);
        $tmpPath = $tmpDir . '/upload_' . bin2hex(random_bytes(6)) . '.xlsx';
        $file->move($tmpDir, basename($tmpPath));

        $validation = service('ticketImportValidator')->validateFile($tmpPath);
        if (! $validation->success) {
            @unlink($tmpPath);
            return redirect()->to(route_to('servicedesk.index'))
                ->with('error', 'La validación falló. Corrige el archivo e inténtalo de nuevo.')
                ->with('rowErrors', $validation->errors);
        }

        // Create the job, then move the file into its own folder.
        $imports = new ServiceDeskImportModel();
        $importId = $imports->insert([
            'name'            => $name,
            'source_filename' => $file->getClientName(),
            'status'          => 'pending',
            'total_rows'      => (int) ($validation->data['totalRows'] ?? 0),
            'uploaded_by'     => session()->get('user_id'),
        ], true);

        $jobDir = WRITEPATH . 'servicedesk/uploads/' . $importId;
        $this->ensureDir($jobDir);
        $sourcePath = $jobDir . '/source.xlsx';
        rename($tmpPath, $sourcePath);

        $logDir = WRITEPATH . 'servicedesk/logs';
        $this->ensureDir($logDir);

        $imports->update($importId, [
            'source_path' => $sourcePath,
            'log_path'    => $logDir . '/import_' . $importId . '.log',
        ]);

        $warnCount = count($validation->data['warnings'] ?? []);
        $msg = "Importación #{$importId} encolada: {$validation->data['totalRows']} tickets."
            . ($warnCount > 0 ? " {$warnCount} avisos (valores que se crearán)." : '');

        return redirect()->to(route_to('servicedesk.imports.show', $importId))->with('success', $msg);
    }

    public function imports(): string
    {
        return view('App\Modules\ServiceDesk\Views\imports', [
            'pageTitle' => 'Historial de importaciones',
            'imports'   => (new ServiceDeskImportModel())->recent(100),
        ]);
    }

    public function show(int $id): string|ResponseInterface
    {
        $import = (new ServiceDeskImportModel())->findWithUser($id);
        if ($import === null) {
            return redirect()->to(route_to('servicedesk.imports.index'))->with('error', 'Importación no encontrada.');
        }
        return view('App\Modules\ServiceDesk\Views\show', [
            'pageTitle' => 'Importación #' . $id,
            'import'    => $import,
        ]);
    }

    /**
     * JSON progress for polling from the detail view.
     */
    public function status(int $id): ResponseInterface
    {
        $import = (new ServiceDeskImportModel())->find($id);
        if ($import === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error']);
        }
        return $this->response->setJSON([
            'status'    => 'success',
            'data'      => [
                'state'     => $import['status'],
                'total'     => (int) $import['total_rows'],
                'processed' => (int) $import['processed_rows'],
                'succeeded' => (int) $import['succeeded_rows'],
                'failed'    => (int) $import['failed_rows'],
                'error'     => $import['error_message'],
                'hasOutput' => ! empty($import['output_path']) && is_file((string) $import['output_path']),
            ],
        ]);
    }

    /**
     * Returns the tail of the job log as plain text.
     */
    public function log(int $id): ResponseInterface
    {
        $import = (new ServiceDeskImportModel())->find($id);
        $path   = $import['log_path'] ?? '';
        if ($import === null || $path === '' || ! is_file($path)) {
            return $this->response->setContentType('text/plain')->setBody('');
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $tail  = implode("\n", array_slice($lines, -300));
        return $this->response->setContentType('text/plain; charset=utf-8')->setBody($tail);
    }

    /**
     * Downloads the result workbook (with TICKET_ID filled in).
     */
    public function downloadOutput(int $id): ResponseInterface
    {
        $import = (new ServiceDeskImportModel())->find($id);
        $path   = $import['output_path'] ?? '';
        if ($import === null || $path === '' || ! is_file($path)) {
            return redirect()->to(route_to('servicedesk.imports.show', $id))
                ->with('error', 'Aún no hay archivo de resultado disponible.');
        }
        return $this->response->download($path, null)
            ->setFileName('resultado_import_' . $id . '.xlsx');
    }

    // ------------------------------------------------------------------

    /**
     * Reads and validates the requested container ids against admin-allowed,
     * active containers.
     *
     * @return int[]
     */
    private function requestedContainers(): array
    {
        $raw = $this->request->getGet('containers') ?? $this->request->getPost('containers');
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        $requested = array_values(array_filter(array_map(
            static fn($v) => (int) trim((string) $v),
            (array) $raw,
        ), static fn($v) => $v > 0));

        if ($requested === []) {
            return [];
        }

        $allowed = array_column(
            $this->introspector()->containerOptions($this->settings()->includedContainerIds()),
            'id'
        );
        return array_values(array_intersect($requested, $allowed));
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
