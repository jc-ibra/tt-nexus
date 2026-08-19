<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use App\Modules\ServiceDesk\Services\TicketImportValidator;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API v1 mirror of the Service Desk web actions.
 */
class ServiceDeskApiController extends BaseApiController
{
    /**
     * GET /api/v1/servicedesk/template?containers=1,3
     * Streams the generated .xlsx template.
     */
    public function template(): ResponseInterface
    {
        $introspector = service('glpiSchemaIntrospector');
        if (! $introspector->isConfigured()) {
            return $this->error('GLPI no está configurado.', 400);
        }

        $ids = $this->containerIds();
        if ($ids === []) {
            return $this->error('Indica al menos un contenedor en el parámetro «containers».', 422);
        }

        $path = WRITEPATH . 'servicedesk/tmp/api_template_' . bin2hex(random_bytes(4)) . '.xlsx';
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        try {
            service('ticketTemplateBuilder')->build($ids, $path);
        } catch (\Throwable $e) {
            return $this->error('No se pudo generar el template: ' . $e->getMessage(), 500);
        }

        return $this->response->download($path, null)->setFileName('template_service_desk.xlsx');
    }

    /**
     * GET /api/v1/servicedesk/schema[?container=3]
     */
    public function schema(): ResponseInterface
    {
        $introspector = service('glpiSchemaIntrospector');
        if (! $introspector->isConfigured()) {
            return $this->error('GLPI no está configurado.', 400);
        }
        $cid = (int) ($this->request->getGet('container') ?? 0);
        if ($cid <= 0) {
            return $this->success($introspector->containerOptions());
        }
        return $this->success($introspector->buildPlan([$cid], false)['columns']);
    }

    /**
     * GET /api/v1/servicedesk/imports
     */
    public function listImports(): ResponseInterface
    {
        return $this->success((new ServiceDeskImportModel())->recent(100, null, 'create'));
    }

    /**
     * GET /api/v1/servicedesk/updates
     */
    public function listUpdates(): ResponseInterface
    {
        return $this->success((new ServiceDeskImportModel())->recent(100, null, 'update'));
    }

    /**
     * GET /api/v1/servicedesk/imports/{id}
     */
    public function showImport(int $id): ResponseInterface
    {
        $import = (new ServiceDeskImportModel())->find($id);
        if ($import === null) {
            return $this->notFound('Importación no encontrada.');
        }
        return $this->success($import);
    }

    /**
     * POST /api/v1/servicedesk/imports  (multipart: file)
     * Validates and enqueues an import job.
     */
    public function createImport(): ResponseInterface
    {
        if (! service('serviceDeskSettings')->importEnabled()) {
            return $this->error('La importación está deshabilitada.', 403);
        }

        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || strtolower($file->getExtension()) !== 'xlsx') {
            return $this->error('Adjunta un archivo .xlsx válido en el campo «file».', 422);
        }

        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $tmpPath = $tmpDir . '/api_upload_' . bin2hex(random_bytes(6)) . '.xlsx';
        $file->move($tmpDir, basename($tmpPath));

        $validation = service('ticketImportValidator')->validateFile($tmpPath);
        if (! $validation->success) {
            @unlink($tmpPath);
            return $this->validationError($validation->errors);
        }

        $imports  = new ServiceDeskImportModel();
        $importId = $imports->insert([
            'name'            => $this->request->getPost('name') ?: $file->getClientName(),
            'source_filename' => $file->getClientName(),
            'status'          => 'pending',
            'mode'            => 'create',
            'total_rows'      => (int) ($validation->data['totalRows'] ?? 0),
            'uploaded_by'     => null,
        ], true);

        $jobDir = WRITEPATH . 'servicedesk/uploads/' . $importId;
        @mkdir($jobDir, 0775, true);
        $sourcePath = $jobDir . '/source.xlsx';
        rename($tmpPath, $sourcePath);

        $logDir = WRITEPATH . 'servicedesk/logs';
        @mkdir($logDir, 0775, true);

        $imports->update($importId, [
            'source_path' => $sourcePath,
            'log_path'    => $logDir . '/import_' . $importId . '.log',
        ]);

        return $this->successCreated([
            'id'       => $importId,
            'status'   => 'pending',
            'total'    => (int) ($validation->data['totalRows'] ?? 0),
            'warnings' => $validation->data['warnings'] ?? [],
        ]);
    }

    /**
     * POST /api/v1/servicedesk/updates  (multipart: file, name?, dry_run?)
     *
     * Mirror of the web update action: validates the Excel in update mode and
     * enqueues a mode=update job on the SAME queue the importer uses, so both
     * engines stay behind the same worker lock.
     *
     * dry_run=1 simulates: computes and reports every change without writing to
     * GLPI. Given how destructive a bulk plancha is, callers should run it once.
     */
    public function createUpdate(): ResponseInterface
    {
        if (! service('serviceDeskSettings')->updateEnabled()) {
            return $this->error('La actualización masiva está deshabilitada.', 403);
        }

        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || strtolower($file->getExtension()) !== 'xlsx') {
            return $this->error('Adjunta un archivo .xlsx válido en el campo «file».', 422);
        }

        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $tmpPath = $tmpDir . '/api_update_' . bin2hex(random_bytes(6)) . '.xlsx';
        $file->move($tmpDir, basename($tmpPath));

        $validation = service('ticketImportValidator')
            ->validateFile($tmpPath, TicketImportValidator::MODE_UPDATE);
        if (! $validation->success) {
            @unlink($tmpPath);
            return $this->validationError($validation->errors);
        }

        $dryRun  = $this->request->getPost('dry_run') ? 1 : 0;
        $imports = new ServiceDeskImportModel();

        $importId = $imports->insert([
            'name'            => $this->request->getPost('name') ?: $file->getClientName(),
            'source_filename' => $file->getClientName(),
            'status'          => 'pending',
            'mode'            => 'update',
            'dry_run'         => $dryRun,
            'total_rows'      => (int) ($validation->data['totalRows'] ?? 0),
            'uploaded_by'     => null,
        ], true);

        $jobDir = WRITEPATH . 'servicedesk/uploads/' . $importId;
        @mkdir($jobDir, 0775, true);
        $sourcePath = $jobDir . '/source.xlsx';
        rename($tmpPath, $sourcePath);

        $logDir = WRITEPATH . 'servicedesk/logs';
        @mkdir($logDir, 0775, true);

        $imports->update($importId, [
            'source_path' => $sourcePath,
            'log_path'    => $logDir . '/update_' . $importId . '.log',
        ]);

        return $this->successCreated([
            'id'        => $importId,
            'status'    => 'pending',
            'mode'      => 'update',
            'dry_run'   => (bool) $dryRun,
            'total'     => (int) ($validation->data['totalRows'] ?? 0),
            'closedIds' => $validation->data['closedIds'] ?? [],
            'warnings'  => $validation->data['warnings'] ?? [],
        ]);
    }

    /**
     * POST /api/v1/servicedesk/updates/{id}/apply
     *
     * Mirror of the web action: takes a FINISHED simulation and enqueues the
     * same workbook for real (dry_run = 0), so what gets applied is exactly what
     * was reviewed. The simulation job is left untouched as evidence.
     */
    public function applyUpdate(int $id): ResponseInterface
    {
        if (! service('serviceDeskSettings')->updateEnabled()) {
            return $this->error('La actualización masiva está deshabilitada.', 403);
        }

        $imports = new ServiceDeskImportModel();
        $job     = $imports->find($id);
        if ($job === null) {
            return $this->notFound('Carga no encontrada.');
        }
        if ((string) ($job['mode'] ?? '') !== 'update' || (int) ($job['dry_run'] ?? 0) !== 1) {
            return $this->error('Solo se puede aplicar una simulación de actualización.', 422);
        }
        if ((string) $job['status'] !== 'ready') {
            return $this->error('La simulación todavía no termina.', 409);
        }

        $source = (string) ($job['source_path'] ?? '');
        if ($source === '' || ! is_file($source)) {
            return $this->error('Ya no está el archivo de la simulación; vuelve a subirlo.', 410);
        }

        $newId = $imports->insert([
            'name'            => 'Aplicación de la simulación #' . $id . ': ' . ($job['name'] ?? ''),
            'source_filename' => $job['source_filename'],
            'status'          => 'pending',
            'mode'            => 'update',
            'dry_run'         => 0,
            'total_rows'      => (int) $job['total_rows'],
            'uploaded_by'     => null,
        ], true);

        $jobDir = WRITEPATH . 'servicedesk/uploads/' . $newId;
        @mkdir($jobDir, 0775, true);
        $newSource = $jobDir . '/source.xlsx';
        if (! @copy($source, $newSource)) {
            $imports->delete($newId);
            return $this->error('No se pudo preparar el archivo para aplicarlo.', 500);
        }

        $logDir = WRITEPATH . 'servicedesk/logs';
        @mkdir($logDir, 0775, true);
        $imports->update($newId, [
            'source_path' => $newSource,
            'log_path'    => $logDir . '/update_' . $newId . '.log',
        ]);

        return $this->successCreated([
            'id'          => $newId,
            'status'      => 'pending',
            'mode'        => 'update',
            'dry_run'     => false,
            'simulatedBy' => $id,
            'total'       => (int) $job['total_rows'],
        ]);
    }

    /**
     * GET /api/v1/servicedesk/backlog/preview
     * Returns the aggregated backlog report data (KPIs, areas, buckets) as JSON,
     * without sending anything. Mirrors what the emailed report contains.
     */
    public function backlogPreview(): ResponseInterface
    {
        $service = service('backlogReportService');
        if (! $service->isConfigured()) {
            return $this->error('GLPI no está configurado.', 400);
        }
        try {
            $data = $service->build();
            unset($data['rows']); // omit the full dump from the preview payload
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->error('No se pudo generar el reporte: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/servicedesk/backlog/send
     * Generates and emails the backlog report now. Optional JSON body:
     *   { "test_email": "a@b.com" }  -> one-off test to that address
     * Otherwise sends to the configured audience (trigger = manual).
     */
    public function backlogSend(): ResponseInterface
    {
        $email = trim((string) ($this->request->getVar('test_email') ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('El correo de prueba no es válido.', 422);
        }

        $result = $email !== ''
            ? service('backlogReportService')->send('test', null, [$email])
            : service('backlogReportService')->send('manual', null);

        return $result->success
            ? $this->success(['message' => $result->message] + (array) $result->data)
            : $this->error($result->message, 500);
    }

    /**
     * @return int[]
     */
    private function containerIds(): array
    {
        $raw = $this->request->getGet('containers');
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        return array_values(array_filter(array_map(
            static fn($v) => (int) trim((string) $v),
            (array) $raw,
        ), static fn($v) => $v > 0));
    }
}
