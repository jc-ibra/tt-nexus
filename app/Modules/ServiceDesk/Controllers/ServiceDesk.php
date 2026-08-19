<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use App\Modules\ServiceDesk\Services\TicketImportValidator;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Operator-facing bulk operations. Download a template for a container
 * selection, upload the filled Excel, and monitor the async job.
 *
 * Two flows share this controller, the queue and the history:
 *   alta masiva      - upload()       -> mode 'create', TICKET_ID lo llena el sistema
 *   plancha + cierre - uploadUpdate() -> mode 'update', TICKET_ID lo llenas tú
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
        $imports    = (new ServiceDeskImportModel())->recent(10, null, 'create');

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
            'mode'            => 'create',
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

    /**
     * Sección de actualización y cierre masivo: se sube el MISMO Excel del
     * importador, pero con TICKET_ID lleno.
     */
    public function updateForm(): string
    {
        $settings = $this->settings();

        return view('App\Modules\ServiceDesk\Views\update', [
            'pageTitle'  => 'Actualizar y cerrar tickets',
            'configured' => $this->introspector()->isConfigured(),
            'settings'   => $settings,
            'imports'    => (new ServiceDeskImportModel())->recent(10, null, 'update'),
        ]);
    }

    /**
     * Receives the filled Excel with TICKET_ID, validates it in update mode and
     * enqueues the job. Same queue and same worker as the importer; only the
     * mode differs.
     */
    public function uploadUpdate(): ResponseInterface
    {
        $redirect = route_to('servicedesk.update');

        if (! $this->settings()->updateEnabled()) {
            return redirect()->to($redirect)
                ->with('error', 'La actualización masiva está deshabilitada por el administrador.');
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to($redirect)->withInput()
                ->with('error', 'Indica un nombre para la carga (ej. el lote de tickets que corriges).');
        }

        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->to($redirect)->with('error', 'Selecciona un archivo .xlsx válido.');
        }
        if (strtolower($file->getExtension()) !== 'xlsx') {
            return redirect()->to($redirect)
                ->with('error', 'El archivo debe ser .xlsx (el template descargado o el resultado del importador).');
        }

        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        $this->ensureDir($tmpDir);
        $tmpPath = $tmpDir . '/upload_' . bin2hex(random_bytes(6)) . '.xlsx';
        $file->move($tmpDir, basename($tmpPath));

        $validation = service('ticketImportValidator')->validateFile($tmpPath, TicketImportValidator::MODE_UPDATE);
        if (! $validation->success) {
            @unlink($tmpPath);
            return redirect()->to($redirect)
                ->with('error', 'La validación falló. Corrige el archivo e inténtalo de nuevo.')
                ->with('rowErrors', $validation->errors);
        }

        $dryRun  = $this->request->getPost('dry_run') ? 1 : 0;
        $imports = new ServiceDeskImportModel();

        $importId = $imports->insert([
            'name'            => $name,
            'source_filename' => $file->getClientName(),
            'status'          => 'pending',
            'mode'            => 'update',
            'dry_run'         => $dryRun,
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
            'log_path'    => $logDir . '/update_' . $importId . '.log',
        ]);

        $total     = (int) ($validation->data['totalRows'] ?? 0);
        $warnCount = count($validation->data['warnings'] ?? []);
        $msg = ($dryRun ? "Simulación #{$importId} encolada" : "Actualización #{$importId} encolada")
            . ": {$total} ticket(s)."
            . ($warnCount > 0 ? " {$warnCount} aviso(s), revísalos en el detalle." : '');

        return redirect()->to(route_to('servicedesk.imports.show', $importId))->with('success', $msg);
    }

    public function imports(): string
    {
        $mode = $this->request->getGet('modo');
        $mode = in_array($mode, ['create', 'update'], true) ? $mode : null;

        return view('App\Modules\ServiceDesk\Views\imports', [
            'pageTitle' => 'Historial de cargas',
            'imports'   => (new ServiceDeskImportModel())->recent(100, null, $mode),
            'mode'      => $mode,
        ]);
    }

    public function show(int $id): string|ResponseInterface
    {
        $import = (new ServiceDeskImportModel())->findWithUser($id);
        if ($import === null) {
            return redirect()->to(route_to('servicedesk.imports.index'))->with('error', 'Importación no encontrada.');
        }
        $isUpdate = (string) ($import['mode'] ?? 'create') === 'update';

        return view('App\Modules\ServiceDesk\Views\show', [
            'pageTitle' => ($isUpdate ? 'Actualización #' : 'Importación #') . $id,
            'import'    => $import,
            'isUpdate'  => $isUpdate,
            'canApply'  => $this->settings()->updateEnabled(),
        ]);
    }

    /**
     * Applies a finished simulation for real, reusing the file already stored
     * with the job instead of making the operator upload it again.
     *
     * Clones the job with dry_run = 0 and a copy of the same source workbook, so
     * lo que se aplica es exactamente lo que se simuló. No se reescribe el
     * trabajo original: la simulación queda como evidencia de lo que se revisó.
     */
    public function applySimulation(int $id): ResponseInterface
    {
        $imports = new ServiceDeskImportModel();
        $job     = $imports->find($id);

        if ($job === null) {
            return redirect()->to(route_to('servicedesk.imports.index'))
                ->with('error', 'Carga no encontrada.');
        }
        if (! $this->settings()->updateEnabled()) {
            return redirect()->to(route_to('servicedesk.imports.show', $id))
                ->with('error', 'La actualización masiva está deshabilitada por el administrador.');
        }
        if ((string) ($job['mode'] ?? '') !== 'update' || (int) ($job['dry_run'] ?? 0) !== 1) {
            return redirect()->to(route_to('servicedesk.imports.show', $id))
                ->with('error', 'Solo se puede aplicar una simulación de actualización.');
        }
        if ((string) $job['status'] !== 'ready') {
            return redirect()->to(route_to('servicedesk.imports.show', $id))
                ->with('error', 'La simulación todavía no termina. Espera a que diga Completada.');
        }

        $source = (string) ($job['source_path'] ?? '');
        if ($source === '' || ! is_file($source)) {
            return redirect()->to(route_to('servicedesk.imports.show', $id))
                ->with('error', 'Ya no está el archivo de la simulación. Vuelve a subirlo sin la casilla de simular.');
        }

        $newId = $imports->insert([
            'name'            => 'Aplicación de la simulación #' . $id . ': ' . ($job['name'] ?? ''),
            'source_filename' => $job['source_filename'],
            'status'          => 'pending',
            'mode'            => 'update',
            'dry_run'         => 0,
            'total_rows'      => (int) $job['total_rows'],
            'uploaded_by'     => session()->get('user_id'),
        ], true);

        $jobDir = WRITEPATH . 'servicedesk/uploads/' . $newId;
        $this->ensureDir($jobDir);
        $newSource = $jobDir . '/source.xlsx';
        if (! @copy($source, $newSource)) {
            $imports->delete($newId);
            return redirect()->to(route_to('servicedesk.imports.show', $id))
                ->with('error', 'No se pudo preparar el archivo para aplicarlo. Intenta subirlo de nuevo.');
        }

        $logDir = WRITEPATH . 'servicedesk/logs';
        $this->ensureDir($logDir);

        $imports->update($newId, [
            'source_path' => $newSource,
            'log_path'    => $logDir . '/update_' . $newId . '.log',
        ]);

        return redirect()->to(route_to('servicedesk.imports.show', $newId))
            ->with('success', "Actualización #{$newId} encolada con el mismo archivo de la simulación #{$id}.");
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
                'mode'      => $import['mode'] ?? 'create',
                'dryRun'    => (int) ($import['dry_run'] ?? 0) === 1,
                'total'     => (int) $import['total_rows'],
                'processed' => (int) $import['processed_rows'],
                'succeeded' => (int) $import['succeeded_rows'],
                'failed'    => (int) $import['failed_rows'],
                'skipped'   => (int) ($import['skipped_rows'] ?? 0),
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
        $prefix = (string) ($import['mode'] ?? 'create') === 'update' ? 'resultado_actualizacion_' : 'resultado_import_';

        return $this->response->download($path, null)->setFileName($prefix . $id . '.xlsx');
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
