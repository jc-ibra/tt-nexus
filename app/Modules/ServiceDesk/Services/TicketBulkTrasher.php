<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Provisioning\Connectors\GlpiConnector;
use App\Modules\Provisioning\Services\ConnectorFactory;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Runs one TRASH or RESTORE job: for every row with a TICKET_ID it moves the
 * ticket to GLPI's trash (mode `trash`) or takes it back out (mode `restore`).
 *
 * Reglas (ver docs/modulos/servicedesk/papelera-masiva.md):
 *
 *  - Solo papelera. Nunca se purga: GLPI conserva el ticket y se puede restaurar.
 *  - Va por la API REST, así que GLPI aplica sus permisos, su historial y sus
 *    notificaciones, igual que una baja manual.
 *  - Tras escribir se relee is_deleted: GLPI puede responder 200 y no hacer nada
 *    (p. ej. el usuario de API sin permiso de eliminar). Eso sale como DESVIACION,
 *    nunca como PAPELERA.
 *  - Un ticket que ya está en el estado destino se omite, no es error.
 *  - Con dry_run no se escribe nada y el resultado va en condicional.
 */
class TicketBulkTrasher
{
    private const SHEET = 'DATOS';

    private const R_TRASHED   = 'PAPELERA';
    private const R_RESTORED  = 'RESTAURADO';
    private const R_ALREADY_T = 'YA EN PAPELERA';
    private const R_ALREADY_A = 'YA ACTIVO';
    private const R_SIM_T     = 'SIMULADO (IRIA A PAPELERA)';
    private const R_SIM_R     = 'SIMULADO (SE RESTAURARIA)';
    private const R_DEVIATION = 'DESVIACION';
    private const R_ERROR     = 'ERROR';

    private const PREV_HEADER = 'ESTADO_PREVIO';

    private string $logPath = '';
    private bool $dryRun    = false;
    private bool $toTrash   = true;

    public function __construct(
        private GlpiDbConnection $glpiDb,
        private ConnectorFactory $connectors,
        private ServiceDeskSettings $settings,
        private ServiceDeskImportModel $imports,
        private ServiceDeskConfig $config,
    ) {}

    /** Same contract as TicketBulkImporter::run() / TicketBulkUpdater::run(). */
    public function run(int $importId): void
    {
        $job = $this->imports->find($importId);
        if ($job === null) {
            throw new \RuntimeException("Lote #{$importId} no encontrado.");
        }

        $this->logPath = (string) ($job['log_path'] ?? '');
        $this->toTrash = ($job['mode'] ?? 'trash') !== 'restore';
        $this->dryRun  = (int) ($job['dry_run'] ?? 0) === 1;
        $this->imports->update($importId, ['status' => 'processing', 'started_at' => date('Y-m-d H:i:s')]);

        try {
            $summary = $this->process($importId, $job);
            $this->imports->update($importId, [
                'status'         => 'ready',
                'processed_rows' => $summary['processed'],
                'succeeded_rows' => $summary['succeeded'],
                'failed_rows'    => $summary['failed'],
                'skipped_rows'   => $summary['skipped'],
                'output_path'    => $summary['outputPath'],
                'finished_at'    => date('Y-m-d H:i:s'),
            ]);

            $verb = $this->toTrash ? 'a la papelera' : 'restaurados';
            $this->log('INFO', $this->dryRun
                ? sprintf(
                    'Simulación finalizada: %d ticket(s) SÍ irían %s, %d se omitirían, %d con problema. '
                    . 'NO se escribió nada en GLPI; para aplicarlo usa el botón "Aplicar en GLPI".',
                    $summary['succeeded'],
                    $verb,
                    $summary['skipped'],
                    $summary['failed'],
                )
                : sprintf(
                    'Finalizado: %d ticket(s) %s, %d omitidos, %d con problema.',
                    $summary['succeeded'],
                    $this->toTrash ? 'enviados a la papelera' : 'restaurados',
                    $summary['skipped'],
                    $summary['failed'],
                ));
        } catch (\Throwable $e) {
            $this->log('ERROR', 'Lote abortado: ' . $e->getMessage());
            $this->imports->update($importId, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
    }

    /**
     * @return array{processed:int,succeeded:int,failed:int,skipped:int,outputPath:string}
     */
    private function process(int $importId, array $job): array
    {
        $path = (string) $job['source_path'];
        if (! is_file($path)) {
            throw new \RuntimeException('No se encontró el archivo de origen.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getSheetByName(self::SHEET) ?? $spreadsheet->getSheet(0);

        $rows      = $sheet->toArray(null, true, false, false);
        $headerRow = array_map(static fn($v) => trim((string) ($v ?? '')), $rows[0] ?? []);
        $headerIdx = [];
        foreach ($headerRow as $i => $name) {
            if ($name !== '') {
                $headerIdx[mb_strtoupper($name)] = $i;
            }
        }

        $idHeader = mb_strtoupper($this->config->ticketIdHeader);
        if (! isset($headerIdx[$idHeader])) {
            throw new \RuntimeException("El Excel no tiene la columna {$this->config->ticketIdHeader}.");
        }
        $idCol = $headerIdx[$idHeader];

        // Columnas de salida: se agregan si la hoja no las trae (un archivo de
        // resultado previo ya las trae y se sobrescriben).
        $nextCol    = count($headerRow);
        $resultCol  = $this->ensureColumn($sheet, $headerIdx, $this->config->updateResultHeader, $nextCol);
        $previewCol = $this->ensureColumn($sheet, $headerIdx, self::PREV_HEADER, $nextCol);

        $batch = $this->settings->batchSize();
        $pause = $this->settings->batchPauseSeconds();

        $connector = $this->connectors->buildByKey('glpi');
        if (! $connector instanceof GlpiConnector) {
            throw new \RuntimeException('El conector de GLPI no está disponible.');
        }

        $session = $connector->openApiSession();
        if (! $session['success']) {
            throw new \RuntimeException('No se pudo iniciar sesión con la API de GLPI: ' . ($session['error'] ?? ''));
        }
        $token = $session['token'];

        $this->log('INFO', sprintf(
            '%s de hasta %d fila(s).',
            $this->dryRun
                ? 'SIMULACIÓN (no se escribe nada en GLPI)'
                : ($this->toTrash ? 'Envío a la papelera' : 'Restauración desde la papelera'),
            max(0, count($rows) - 1),
        ));

        $processed = 0;
        $succeeded = 0;
        $failed    = 0;
        $skipped   = 0;

        try {
            for ($r = 1; $r < count($rows); $r++) {
                $excelRow = $r + 1;
                $rawId    = trim((string) ($rows[$r][$idCol] ?? ''));
                if ($rawId === '') {
                    continue; // fila vacía (o sin id: el validador ya la habría rechazado)
                }
                $ticketId = ctype_digit($rawId) ? (int) $rawId : 0;

                try {
                    if ($ticketId <= 0) {
                        throw new \RuntimeException("«{$rawId}» no es un id de ticket válido.");
                    }

                    $outcome = $this->applyRow($connector, $token, $ticketId);

                    $sheet->setCellValue([$resultCol + 1, $excelRow], $outcome['result']);
                    $sheet->setCellValueExplicit([$previewCol + 1, $excelRow], $outcome['previous'], DataType::TYPE_STRING);

                    if (in_array($outcome['result'], [self::R_ALREADY_T, self::R_ALREADY_A], true)) {
                        $skipped++;
                        $this->log('INFO', "[{$excelRow}] #{$ticketId}: {$outcome['result']}, se omite.");
                    } elseif ($outcome['result'] === self::R_DEVIATION) {
                        $failed++;
                        $this->log('WARN', "[{$excelRow}] #{$ticketId}: DESVIACION. {$outcome['detail']}");
                    } else {
                        $succeeded++;
                        $this->log('INFO', "[{$excelRow}] #{$ticketId}: {$outcome['result']}.");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $sheet->setCellValue([$resultCol + 1, $excelRow], self::R_ERROR . ': ' . $e->getMessage());
                    $this->log('ERROR', "[{$excelRow}] #{$ticketId}: " . $e->getMessage());
                }

                $processed++;
                if ($processed % $batch === 0) {
                    $this->imports->update($importId, [
                        'processed_rows' => $processed,
                        'succeeded_rows' => $succeeded,
                        'failed_rows'    => $failed,
                        'skipped_rows'   => $skipped,
                    ]);
                    $this->log('INFO', "Lote completo ({$processed} procesados). Pausa {$pause}s.");
                    if ($pause > 0 && ! $this->dryRun) {
                        sleep($pause);
                    }
                }
            }
        } finally {
            $connector->closeApiSession($token);
        }

        $outputPath = $this->outputPathFor($path);
        (new XlsxWriter($spreadsheet))->save($outputPath);

        return [
            'processed'  => $processed,
            'succeeded'  => $succeeded,
            'failed'     => $failed,
            'skipped'    => $skipped,
            'outputPath' => $outputPath,
        ];
    }

    /**
     * @return array{result:string,previous:string,detail:string}
     */
    private function applyRow(GlpiConnector $connector, string $token, int $ticketId): array
    {
        $state = $this->readState($ticketId);
        if ($state === null) {
            throw new \RuntimeException('El ticket no existe en GLPI.');
        }

        $previous = $state['deleted'] ? 'EN PAPELERA' : 'ACTIVO';

        // Ya está donde se pide: nada que hacer.
        if ($state['deleted'] === $this->toTrash) {
            return [
                'result'   => $this->toTrash ? self::R_ALREADY_T : self::R_ALREADY_A,
                'previous' => $previous,
                'detail'   => '',
            ];
        }

        if ($this->dryRun) {
            return [
                'result'   => $this->toTrash ? self::R_SIM_T : self::R_SIM_R,
                'previous' => $previous,
                'detail'   => '',
            ];
        }

        $res = $this->toTrash
            ? $connector->trashTicket($ticketId, $token)
            : $connector->restoreTicket($ticketId, $token);
        if (! $res->success) {
            throw new \RuntimeException($res->message);
        }

        // GLPI puede contestar 200 y no hacer nada: se relee antes de afirmar.
        $after = $this->readState($ticketId);
        if ($after !== null && $after['deleted'] === $this->toTrash) {
            return [
                'result'   => $this->toTrash ? self::R_TRASHED : self::R_RESTORED,
                'previous' => $previous,
                'detail'   => '',
            ];
        }

        $glpiMsg = (string) ($res->payload['glpiMessage'] ?? '');

        return [
            'result'   => self::R_DEVIATION,
            'previous' => $previous,
            'detail'   => 'GLPI respondió sin error pero el ticket sigue '
                . ($this->toTrash ? 'activo' : 'en la papelera')
                . ($glpiMsg !== '' ? ". Mensaje de GLPI: {$glpiMsg}" : '')
                . '. Revisa que el usuario de la API tenga permiso para '
                . ($this->toTrash ? 'eliminar' : 'restaurar') . ' tickets.',
        ];
    }

    /** @return array{deleted:bool}|null null when the ticket does not exist */
    private function readState(int $ticketId): ?array
    {
        $row = $this->glpiDb->connection()
            ->table('glpi_tickets')
            ->select('id, is_deleted')
            ->where('id', $ticketId)
            ->get()->getRowArray();

        return $row === null ? null : ['deleted' => (int) $row['is_deleted'] === 1];
    }

    /**
     * @param array<string,int> $headerIdx by reference: gains the new column
     * @param int               $nextCol   by reference: next free 0-based index
     */
    private function ensureColumn(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array &$headerIdx, string $header, int &$nextCol): int
    {
        $key = mb_strtoupper($header);
        if (isset($headerIdx[$key])) {
            return $headerIdx[$key];
        }
        $sheet->setCellValue([$nextCol + 1, 1], $header);
        $headerIdx[$key] = $nextCol;

        return $nextCol++;
    }

    private function outputPathFor(string $sourcePath): string
    {
        $dir  = dirname($sourcePath);
        $stem = pathinfo($sourcePath, PATHINFO_FILENAME);

        return $dir . DIRECTORY_SEPARATOR . $stem . '_resultado.xlsx';
    }

    private function log(string $level, string $message): void
    {
        $line = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;
        if ($this->logPath !== '') {
            @file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
        }
        log_message('info', '[ServiceDesk][trash] ' . trim($line));
    }
}
