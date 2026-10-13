<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Orchestrates the trash / restore jobs for BOTH the web controller and the API
 * controller, so the two cannot drift apart. The heavy lifting (talking to GLPI)
 * is TicketBulkTrasher, run later by the worker; this class only validates,
 * stores the workbook and enqueues the job on the shared servicedesk_imports table.
 *
 * Failures carry an HTTP-ish `code` in ServiceResult::data so the API can map
 * them without re-deriving the reason (404 / 409 / 410 / 422 / 500).
 */
class TrashJobService
{
    public const MODE_TRASH   = 'trash';
    public const MODE_RESTORE = 'restore';

    public function __construct(
        private TicketImportValidator $validator,
        private ServiceDeskImportModel $imports,
        private ServiceDeskConfig $config,
    ) {}

    /**
     * Validates an uploaded workbook and enqueues a trash job. The caller has
     * already moved the file to $tmpPath; on success it is moved into the job
     * folder, on failure it is deleted.
     *
     * @return ServiceResult data on success: id, total, warnings, openIds, skipIds, dryRun
     */
    public function enqueueUpload(string $tmpPath, string $clientName, string $name, bool $dryRun, ?int $userId): ServiceResult
    {
        $validation = $this->validator->validateTrashFile($tmpPath, TicketImportValidator::MODE_TRASH);
        if (! $validation->success) {
            @unlink($tmpPath);
            return $validation;
        }

        $id = $this->imports->insert([
            'name'            => $name !== '' ? $name : $clientName,
            'source_filename' => $clientName,
            'status'          => 'pending',
            'mode'            => self::MODE_TRASH,
            'dry_run'         => $dryRun ? 1 : 0,
            'total_rows'      => (int) ($validation->data['totalRows'] ?? 0),
            'uploaded_by'     => $userId,
        ], true);

        $source = $this->jobDir((int) $id) . '/source.xlsx';
        if (! @rename($tmpPath, $source)) {
            $this->imports->delete($id);
            @unlink($tmpPath);
            return ServiceResult::fail('No se pudo guardar el archivo cargado.', ['code' => 500]);
        }

        $this->imports->update($id, [
            'source_path' => $source,
            'log_path'    => $this->logDir() . '/trash_' . $id . '.log',
        ]);

        return ServiceResult::ok([
            'id'       => (int) $id,
            'total'    => (int) ($validation->data['totalRows'] ?? 0),
            'warnings' => $validation->data['warnings'] ?? [],
            'openIds'  => $validation->data['openIds'] ?? [],
            'skipIds'  => $validation->data['skipIds'] ?? [],
            'dryRun'   => $dryRun,
        ]);
    }

    /**
     * Applies a FINISHED simulation for real, re-using the stored workbook. The
     * simulation job is left untouched as evidence of what was reviewed.
     *
     * @return ServiceResult data on success: id, simulatedBy, total
     */
    public function applySimulation(int $id, ?int $userId): ServiceResult
    {
        $job = $this->imports->find($id);
        if ($job === null) {
            return ServiceResult::fail('Carga no encontrada.', ['code' => 404]);
        }
        if ((string) ($job['mode'] ?? '') !== self::MODE_TRASH || (int) ($job['dry_run'] ?? 0) !== 1) {
            return ServiceResult::fail('Solo se puede aplicar una simulación de papelera.', ['code' => 422]);
        }
        if ((string) $job['status'] !== 'ready') {
            return ServiceResult::fail('La simulación todavía no termina. Espera a que diga Completada.', ['code' => 409]);
        }

        $source = (string) ($job['source_path'] ?? '');
        if ($source === '' || ! is_file($source)) {
            return ServiceResult::fail('Ya no está el archivo de la simulación. Vuelve a subirlo sin la casilla de simular.', ['code' => 410]);
        }

        $newId = $this->imports->insert([
            'name'            => 'Aplicación de la simulación #' . $id . ': ' . ($job['name'] ?? ''),
            'source_filename' => $job['source_filename'],
            'status'          => 'pending',
            'mode'            => self::MODE_TRASH,
            'dry_run'         => 0,
            'total_rows'      => (int) $job['total_rows'],
            'uploaded_by'     => $userId,
        ], true);

        $newSource = $this->jobDir((int) $newId) . '/source.xlsx';
        if (! @copy($source, $newSource)) {
            $this->imports->delete($newId);
            return ServiceResult::fail('No se pudo preparar el archivo para aplicarlo. Intenta subirlo de nuevo.', ['code' => 500]);
        }

        $this->imports->update($newId, [
            'source_path' => $newSource,
            'log_path'    => $this->logDir() . '/trash_' . $newId . '.log',
        ]);

        return ServiceResult::ok(['id' => (int) $newId, 'simulatedBy' => $id, 'total' => (int) $job['total_rows']]);
    }

    /**
     * Enqueues a RESTORE job for the tickets a finished, applied trash job
     * actually sent to the trash (rows whose RESULTADO is exactly PAPELERA).
     * Rows that were skipped, deviated or errored are not touched.
     *
     * @return ServiceResult data on success: id, restoredFrom, total
     */
    public function restoreBatch(int $id, ?int $userId): ServiceResult
    {
        $job = $this->imports->find($id);
        if ($job === null) {
            return ServiceResult::fail('Carga no encontrada.', ['code' => 404]);
        }
        if ((string) ($job['mode'] ?? '') !== self::MODE_TRASH || (int) ($job['dry_run'] ?? 0) !== 0) {
            return ServiceResult::fail('Solo se puede restaurar un lote de papelera ya aplicado (no una simulación).', ['code' => 422]);
        }
        if ((string) $job['status'] !== 'ready') {
            return ServiceResult::fail('El lote todavía no termina.', ['code' => 409]);
        }

        $output = (string) ($job['output_path'] ?? '');
        if ($output === '' || ! is_file($output)) {
            return ServiceResult::fail('Ya no está el archivo de resultado del lote.', ['code' => 410]);
        }

        $ids = $this->trashedIdsFromResult($output);
        if ($ids === []) {
            return ServiceResult::fail('Este lote no dejó ningún ticket en la papelera, no hay nada que restaurar.', ['code' => 422]);
        }

        $newId = $this->imports->insert([
            'name'            => 'Restauración del lote #' . $id . ': ' . ($job['name'] ?? ''),
            'source_filename' => 'restauracion_lote_' . $id . '.xlsx',
            'status'          => 'pending',
            'mode'            => self::MODE_RESTORE,
            'dry_run'         => 0,
            'total_rows'      => count($ids),
            'uploaded_by'     => $userId,
        ], true);

        $source = $this->jobDir((int) $newId) . '/source.xlsx';
        $this->writeIdWorkbook($ids, $source);

        $this->imports->update($newId, [
            'source_path' => $source,
            'log_path'    => $this->logDir() . '/restore_' . $newId . '.log',
        ]);

        return ServiceResult::ok(['id' => (int) $newId, 'restoredFrom' => $id, 'total' => count($ids)]);
    }

    /** Minimal template: DATOS with TICKET_ID + MOTIVO, and a short how-to sheet. */
    public function buildTemplate(string $path): void
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('DATOS');
        $sheet->setCellValue('A1', $this->config->ticketIdHeader);
        $sheet->setCellValue('B1', 'MOTIVO');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(60);

        $help = $book->createSheet();
        $help->setTitle('INSTRUCCIONES');
        $lines = [
            'Papelera masiva de tickets',
            '',
            '1. Escribe un TICKET_ID por fila en la hoja DATOS (el id numérico del ticket en GLPI).',
            '2. MOTIVO es opcional y solo sirve de referencia para quien revise el archivo; no se envía a GLPI.',
            '3. Sube el archivo con la casilla de simulación marcada y revisa el resultado.',
            '4. Si todo se ve bien, usa "Aplicar en GLPI" sobre la simulación.',
            '5. Los tickets quedan en la papelera de GLPI (no se borran) y se pueden restaurar desde el detalle del lote.',
        ];
        foreach ($lines as $i => $text) {
            $help->setCellValue([1, $i + 1], $text);
        }
        $help->getStyle('A1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(110);
        $book->setActiveSheetIndex(0);

        (new XlsxWriter($book))->save($path);
    }

    // ------------------------------------------------------------------

    /** @return int[] */
    private function trashedIdsFromResult(string $outputPath): array
    {
        $book  = IOFactory::load($outputPath);
        $sheet = $book->getSheetByName('DATOS') ?? $book->getSheet(0);
        $rows  = $sheet->toArray(null, true, false, false);

        $idCol  = null;
        $resCol = null;
        foreach (($rows[0] ?? []) as $i => $name) {
            $key = mb_strtoupper(trim((string) ($name ?? '')));
            if ($key === mb_strtoupper($this->config->ticketIdHeader)) {
                $idCol = $i;
            } elseif ($key === mb_strtoupper($this->config->updateResultHeader)) {
                $resCol = $i;
            }
        }
        if ($idCol === null || $resCol === null) {
            return [];
        }

        $ids = [];
        for ($r = 1; $r < count($rows); $r++) {
            if (trim((string) ($rows[$r][$resCol] ?? '')) === 'PAPELERA') {
                $id = (int) trim((string) ($rows[$r][$idCol] ?? ''));
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /** @param int[] $ids */
    private function writeIdWorkbook(array $ids, string $path): void
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('DATOS');
        $sheet->setCellValue('A1', $this->config->ticketIdHeader);
        foreach ($ids as $i => $id) {
            $sheet->setCellValue([1, $i + 2], $id);
        }
        (new XlsxWriter($book))->save($path);
    }

    private function jobDir(int $id): string
    {
        $dir = WRITEPATH . 'servicedesk/uploads/' . $id;
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function logDir(): string
    {
        $dir = WRITEPATH . 'servicedesk/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
