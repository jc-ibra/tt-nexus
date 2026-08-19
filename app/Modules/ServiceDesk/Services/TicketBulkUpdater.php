<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Models\UserModel;
use App\Modules\Provisioning\Connectors\ConnectorResult;
use App\Modules\Provisioning\Connectors\GlpiConnector;
use App\Modules\Provisioning\Services\ConnectorFactory;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Runs one UPDATE job: reads the same Excel the importer produces/consumes, and
 * for every row that carries a TICKET_ID it "plancha" the filled cells onto the
 * existing GLPI ticket, closing it when ESTATUS says so.
 *
 * Reglas de oro (decididas con el usuario, ver docs/servicedesk-actualizacion-masiva.md):
 *
 *  - Celda llena = se escribe. Celda vacía = NO se toca. `[VACIAR]` borra.
 *    Esto es lo que permite subir un Excel con solo dos columnas corregidas.
 *  - Cerrar = ITILSolution + estatus, nunca un PUT pelón de estatus (dejaría
 *    tickets cerrados sin solución, que GLPI reporta como anómalos).
 *  - Un ticket cerrado se REABRE para poder escribirle y se vuelve a cerrar
 *    conservando su fecha de cierre original. GLPI bloquea la edición de cerrados.
 *  - Tras escribir se relee el ticket: si GLPI (o sus reglas de negocio) no
 *    respetó un valor, la fila sale como DESVIACION en vez de mentir "ACTUALIZADO".
 *
 * Los campos base van por la API REST (respeta reglas de negocio, historial y
 * notificaciones); las filas del contenedor del plugin van directo a la BD de
 * GLPI con el esquema introspectado, igual que hace el importador.
 */
class TicketBulkUpdater
{
    private const SHEET = 'DATOS';

    /** Resultados posibles por fila (columna RESULTADO del archivo de salida). */
    private const R_UPDATED   = 'ACTUALIZADO';
    private const R_UNCHANGED = 'SIN CAMBIOS';
    private const R_DEVIATION = 'DESVIACION';
    private const R_SIMULATED = 'SIMULADO';
    private const R_ERROR     = 'ERROR';

    private string $logPath = '';

    // Opciones de la corrida, fijadas en process() para no arrastrarlas por
    // media docena de firmas.
    private bool $dryRun        = false;
    private bool $reopenClosed  = true;
    private bool $verifyWrites  = true;
    private bool $rehomologate  = false;
    private bool $autocreate    = false;
    private int  $entities      = 0;
    private int  $authorUserId  = 0;
    private string $solutionText = '';

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
        private GlpiDbConnection $glpiDb,
        private GlpiValueResolver $resolver,
        private ConnectorFactory $connectors,
        private ServiceDeskSettings $settings,
        private ServiceDeskImportModel $imports,
        private ServiceDeskConfig $config,
    ) {}

    /**
     * Processes the given job id. Updates the job row as it runs, exactly like
     * TicketBulkImporter::run() so the worker and the UI treat both the same.
     */
    public function run(int $importId): void
    {
        $job = $this->imports->find($importId);
        if ($job === null) {
            throw new \RuntimeException("Actualización #{$importId} no encontrada.");
        }

        $this->logPath = (string) ($job['log_path'] ?? '');
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
            $this->log('INFO', $this->dryRun
                ? sprintf(
                    'Simulación finalizada: %d ticket(s) SÍ cambiarían, %d ya están como dice el Excel, '
                    . '%d con problema. NO se escribió nada en GLPI; para aplicarlo usa el botón '
                    . '"Aplicar en GLPI".',
                    $summary['succeeded'],
                    $summary['skipped'],
                    $summary['failed'],
                )
                : sprintf(
                    'Actualización finalizada: %d ticket(s) quedaron tal cual pedía el Excel, %d ya estaban así, '
                    . '%d con algo que revisar (esos sí recibieron cambios; lo que no se quedó viene en su línea).',
                    $summary['succeeded'],
                    $summary['skipped'],
                    $summary['failed'],
                ));
        } catch (\Throwable $e) {
            $this->log('ERROR', 'Actualización abortada: ' . $e->getMessage());
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
        $containers  = TicketTemplateBuilder::readMetaContainers($spreadsheet);
        if ($containers === []) {
            throw new \RuntimeException('El archivo no tiene metadatos de contenedor.');
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET);
        if ($sheet === null) {
            throw new \RuntimeException('El Excel no tiene la hoja ' . self::SHEET . '.');
        }

        $plan    = $this->introspector->buildPlan($containers, false);
        $columns = $plan['columns'];

        // formatData=false: same reading contract as the validator and the importer.
        $rows      = $sheet->toArray(null, true, false, false);
        $headerRow = array_map(static fn($v) => trim((string) ($v ?? '')), $rows[0]);
        $headerIdx = [];
        foreach ($headerRow as $i => $name) {
            if ($name !== '') {
                $headerIdx[$name] = $i;
            }
        }

        $idHeader = $this->config->ticketIdHeader;
        if (! isset($headerIdx[$idHeader])) {
            throw new \RuntimeException("El Excel no tiene la columna {$idHeader}.");
        }

        // Output columns: appended if the sheet does not carry them yet.
        $nextCol    = count($headerRow);
        $resultCol  = $this->ensureColumn($sheet, $headerIdx, $this->config->updateResultHeader, $nextCol);
        $changesCol = $this->ensureColumn($sheet, $headerIdx, $this->config->updateChangesHeader, $nextCol);
        $outputHeaders = [$this->config->updateResultHeader, $this->config->updateChangesHeader];

        [$baseCols, $pluginByContainer] = $this->splitColumns($columns);
        $sucursalHeader = $this->findSucursalHeader($columns);

        // Runtime options.
        $this->dryRun       = (int) ($job['dry_run'] ?? 0) === 1;
        $this->reopenClosed = $this->settings->updateReopenClosed();
        $this->verifyWrites = $this->settings->updateVerifyWrites();
        $this->rehomologate = $this->settings->updateRehomologateTitle();
        $this->autocreate   = $this->settings->autocreateCatalogValues();
        $this->entities     = $this->settings->entitiesId();
        $this->solutionText = $this->settings->updateSolutionText();
        $this->authorUserId = $this->resolveAuthor($job);

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

        $totalRows = max(0, count($rows) - 1);
        $this->log('INFO', sprintf(
            '%s de hasta %d fila(s) sobre %d contenedor(es). Reabrir cerrados: %s. Verificar escrituras: %s.',
            $this->dryRun ? 'SIMULACIÓN (no se escribe nada en GLPI)' : 'Actualización',
            $totalRows,
            count($containers),
            $this->reopenClosed ? 'sí' : 'no',
            $this->verifyWrites ? 'sí' : 'no',
        ));

        $processed = 0;
        $succeeded = 0;
        $failed    = 0;
        $skipped   = 0;

        try {
            for ($r = 1; $r < count($rows); $r++) {
                $excelRow = $r + 1;
                $rowArr   = $rows[$r];

                $f = [];
                foreach ($headerIdx as $header => $idx) {
                    $f[$header] = $rowArr[$idx] ?? null;
                }

                // Skip fully empty rows. Las columnas de salida de una corrida
                // anterior no cuentan como contenido: si no, al reprocesar un
                // archivo de resultado una fila vacía con "SIN CAMBIOS" pegado
                // se leería como fila real y saldría como ERROR.
                $hasContent = false;
                foreach ($f as $header => $v) {
                    if (in_array($header, $outputHeaders, true)) {
                        continue;
                    }
                    if (trim((string) ($v ?? '')) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (! $hasContent) {
                    continue;
                }

                $ticketId = (int) trim((string) ($f[$idHeader] ?? ''));

                try {
                    if ($ticketId <= 0) {
                        throw new \RuntimeException("Sin {$idHeader}: este flujo no crea tickets.");
                    }

                    $outcome = $this->applyRow($connector, $token, $ticketId, $f, $baseCols, $pluginByContainer, $sucursalHeader);

                    $sheet->setCellValue([$resultCol + 1, $excelRow], $outcome['result']);
                    $sheet->setCellValueExplicit(
                        [$changesCol + 1, $excelRow],
                        implode('; ', $outcome['changes']),
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                    );

                    if ($outcome['result'] === self::R_UNCHANGED) {
                        $skipped++;
                        $this->log('INFO', "[{$excelRow}] #{$ticketId}: ya está como dice el Excel, no hay nada que cambiar.");
                    } elseif ($outcome['result'] === self::R_DEVIATION) {
                        $failed++;
                        $this->log('WARN', sprintf(
                            '[%d] #%d: DESVIACION. %s || Sí se aplicó: %s',
                            $excelRow,
                            $ticketId,
                            implode(' | ', $outcome['deviations']),
                            $outcome['changes'] === [] ? '(nada)' : implode('; ', $outcome['changes']),
                        ));
                    } else {
                        $succeeded++;
                        $detail = implode('; ', $outcome['changes']);
                        $this->log('INFO', $this->dryRun
                            ? sprintf(
                                '[%d] #%d: %s campo(s) cambiarían%s (ensayo, no se escribió). %s',
                                $excelRow,
                                $ticketId,
                                count($outcome['changes']),
                                str_contains($outcome['result'], 'CERRARIA') ? ' y el ticket se cerraría' : '',
                                $detail,
                            )
                            : "[{$excelRow}] #{$ticketId}: {$outcome['result']}. " . $detail);
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

    // ------------------------------------------------------------------
    // Una fila
    // ------------------------------------------------------------------

    /**
     * @return array{result:string,changes:string[],deviations:string[]}
     */
    private function applyRow(
        GlpiConnector $connector,
        string $token,
        int $ticketId,
        array $f,
        array $baseCols,
        array $pluginByContainer,
        ?string $sucursalHeader,
    ): array {
        // 1. Estado actual: base del diff y de la auditoría.
        $notices = [];   // lo que GLPI conteste en el cuerpo de sus respuestas 200

        $read = $connector->getTicket($ticketId, $token);
        if (! $read->success) {
            throw new \RuntimeException('No se pudo leer el ticket en GLPI: ' . $read->message);
        }
        $ticket    = (array) $read->payload;
        $curStatus = (int) ($ticket['status'] ?? 0);
        $wasClosed = in_array($curStatus, $this->closedStatusIds(), true);

        // 2. Campos base (sin estatus ni fecha de cierre: esos son el paso de cierre).
        [$payload, $changes, $assignPlan] = $this->buildUpdatePayload($f, $baseCols, $ticket, $sucursalHeader, $ticketId);

        // 3. Filas del contenedor del plugin.
        $pluginPlans = [];
        foreach ($pluginByContainer as $containerId => $planCols) {
            $planned = $this->planPluginRow($containerId, $planCols, $f, $ticketId);
            if ($planned['set'] !== []) {
                $pluginPlans[$containerId] = $planned;
                $changes = array_merge($changes, $planned['changes']);
            }
        }

        // 4. Estatus destino.
        $statusHeader = $baseCols['status']['header'] ?? null;
        $statusLabel  = ($statusHeader !== null && $this->filled($f[$statusHeader] ?? null))
            ? ValueParser::norm($f[$statusHeader])
            : null;
        $targetStatus = $statusLabel !== null ? ($this->config->ticketStatuses[$statusLabel] ?? null) : null;
        if ($statusLabel !== null && $targetStatus === null) {
            throw new \RuntimeException("Estatus no reconocido: «{$statusLabel}».");
        }
        $statusChanges = $targetStatus !== null && $targetStatus !== $curStatus;
        $closing       = $statusLabel !== null && in_array($statusLabel, $this->config->closedStatuses, true);

        if ($statusChanges) {
            $changes[] = sprintf(
                '%s: %s -> %s',
                $statusHeader,
                $this->config->glpiStatusLabels[$curStatus] ?? $curStatus,
                $this->config->glpiStatusLabels[$targetStatus] ?? $targetStatus,
            );
        }

        if ($payload === [] && $pluginPlans === [] && $assignPlan === null && ! $statusChanges) {
            return ['result' => self::R_UNCHANGED, 'changes' => [], 'deviations' => []];
        }

        // 5. Simulación: todo lo anterior fue lectura, aquí se corta.
        if ($this->dryRun) {
            return [
                'result'     => self::R_SIMULATED . ($closing ? ' (CAMBIARIA Y LO DEJARIA EN ' . $statusLabel . ')' : ' (CAMBIARIA)'),
                'changes'    => $changes,
                'deviations' => [],
            ];
        }

        // 6. Reapertura: GLPI bloquea la edición de tickets cerrados.
        $reopened = false;
        if ($wasClosed && $payload !== []) {
            if ($this->reopenClosed) {
                $res = $this->put($connector, $token, $ticketId, ['status' => $this->config->reopenStatusId], $notices);
                if (! $res->success) {
                    throw new \RuntimeException('No se pudo reabrir el ticket para corregirlo: ' . $res->message);
                }
                $reopened = true;
            } else {
                $this->log('WARN', "#{$ticketId} está cerrado y la reapertura está deshabilitada; GLPI puede rechazar la escritura.");
            }
        }

        // 7. Campos base.
        if ($payload !== []) {
            $res = $this->put($connector, $token, $ticketId, $payload, $notices);
            if (! $res->success) {
                throw new \RuntimeException('GLPI rechazó la actualización: ' . $res->message);
            }
        }

        // 7.b Reemplazo del técnico: el PUT solo agrega, aquí se quita el anterior.
        if ($assignPlan !== null) {
            $this->reconcileAssignees($connector, $token, $ticketId, $assignPlan);
        }

        // 8. Contenedores del plugin (BD directa, igual que el importador).
        foreach ($pluginPlans as $containerId => $planned) {
            $this->writePluginRow($containerId, $planned, $ticketId);
        }

        // 9. Cierre / estatus / restauración.
        $expectedStatus = null;
        if ($closing) {
            if ($this->ensureSolution($connector, $token, $ticketId, $f)) {
                $changes[] = 'SOLUCION: registrada';
            }

            ['date' => $closeDate, 'explicit' => $explicitClose] = $this->closeDateFor($f, $baseCols, $ticket);
            $isClosed = $targetStatus === ($this->config->ticketStatuses['CERRADO'] ?? 6);
            $final    = ['status' => $targetStatus, 'solvedate' => $closeDate];
            if ($isClosed) {
                $final['closedate'] = $closeDate;
            }
            $res = $this->put($connector, $token, $ticketId, $final, $notices);
            if (! $res->success) {
                throw new \RuntimeException('Se escribieron los datos pero GLPI rechazó el cierre: ' . $res->message);
            }
            $expectedStatus = $targetStatus;

            // GLPI acaba de sellar closedate con la hora de la carga, descartando la
            // fecha enviada. Cuando el operador pidió una concreta, se restituye.
            if ($isClosed && $explicitClose) {
                $stamped = $this->currentCloseDate($ticketId);
                if ($stamped !== $closeDate) {
                    $this->stampDates($ticketId, null, $closeDate);
                    $changes[] = "FECHA_CIERRE: {$closeDate} (GLPI la había sellado con {$stamped})";
                }
            }
        } elseif ($statusChanges) {
            $res = $this->put($connector, $token, $ticketId, ['status' => $targetStatus], $notices);
            if (! $res->success) {
                throw new \RuntimeException('GLPI rechazó el cambio de estatus: ' . $res->message);
            }
            $expectedStatus = $targetStatus;
        } elseif ($reopened) {
            // La fila no pidió cambiar el estatus: se devuelve el ticket a como
            // estaba, con sus fechas originales (GLPI las limpia al reabrir).
            $restore = ['status' => $curStatus];
            foreach (['solvedate', 'closedate'] as $dateKey) {
                if (! empty($ticket[$dateKey])) {
                    $restore[$dateKey] = $ticket[$dateKey];
                }
            }
            $res = $this->put($connector, $token, $ticketId, $restore, $notices);
            if (! $res->success) {
                throw new \RuntimeException(
                    'Los datos se escribieron pero el ticket quedó REABIERTO: GLPI rechazó devolverlo a su estatus. '
                    . $res->message
                );
            }
            // Al re-cerrar, GLPI vuelve a sellar closedate: se restituye la original.
            if (in_array($curStatus, $this->closedStatusIds(), true)) {
                $this->stampDates($ticketId, $ticket['solvedate'] ?? null, $ticket['closedate'] ?? null);
            }
            $changes[] = 'Reabierto y vuelto a cerrar conservando su fecha original';
            $expectedStatus = $curStatus;
        }

        // 10. Verificación: ¿se quedó lo que mandamos?
        $deviations = [];
        if ($this->verifyWrites) {
            $deviations = $this->verify($connector, $token, $ticketId, $payload, $pluginPlans, $expectedStatus, $assignPlan);
        }
        if ($notices !== []) {
            $deviations[] = 'GLPI respondió: ' . implode(' | ', $notices);
        }

        if ($deviations !== []) {
            return ['result' => self::R_DEVIATION, 'changes' => $changes, 'deviations' => $deviations];
        }

        return [
            // La etiqueta es el estatus que pidió el operador (RESUELTO / CERRADO),
            // no un genérico: decir "CERRADO" en una fila que pedía RESUELTO hace
            // desconfiar del reporte entero.
            'result'     => $closing ? (string) $statusLabel : self::R_UPDATED,
            'changes'    => $changes,
            'deviations' => [],
        ];
    }

    /**
     * Envía un PUT y recoge el mensaje que GLPI adjunta a la respuesta 200.
     *
     * GLPI puede descartar un campo inválido, guardar el resto y contestar 200
     * con "Datos no válidos. Actualización cancelada" en el cuerpo. Ese aviso es
     * la única señal de un rechazo parcial, así que se acumula por fila y acaba
     * en el reporte en las palabras exactas de GLPI.
     *
     * @param string[] $notices por referencia
     */
    private function put(GlpiConnector $connector, string $token, int $ticketId, array $payload, array &$notices): ConnectorResult
    {
        $res     = $connector->updateTicket($ticketId, $payload, $token);
        $message = is_array($res->payload) ? trim((string) ($res->payload['glpiMessage'] ?? '')) : '';
        if ($message !== '' && ! in_array($message, $notices, true)) {
            $notices[] = $message;
        }

        return $res;
    }

    // ------------------------------------------------------------------
    // Campos base
    // ------------------------------------------------------------------

    /**
     * Sparse payload: only the keys whose cell is filled AND whose value really
     * differs from the live ticket. Status and closedate are deliberately absent
     * (they belong to the close step).
     *
     * @return array{0:array<string,mixed>,1:string[]} [payload, changes]
     */
    private function buildUpdatePayload(array $f, array $baseCols, array $ticket, ?string $sucursalHeader, int $ticketId): array
    {
        $payload = [];
        $changes = [];

        $cell = function (string $glpiKey) use ($f, $baseCols) {
            $header = $baseCols[$glpiKey]['header'] ?? null;
            return $header !== null ? ($f[$header] ?? null) : null;
        };
        $head = static fn(string $glpiKey) => $baseCols[$glpiKey]['header'] ?? $glpiKey;

        // --- CATEGORIA -------------------------------------------------------
        $newCategoryId = null;
        if (isset($baseCols['itilcategories_id']) && $this->filled($cell('itilcategories_id'))) {
            $raw = $cell('itilcategories_id');
            if ($this->isClear($raw)) {
                $newCategoryId = 0;
            } else {
                $newCategoryId = $this->resolver->categoryId(ValueParser::str($raw));
                if ($newCategoryId === 0) {
                    throw new \RuntimeException('Categoría no encontrada en GLPI: «' . trim((string) $raw) . '».');
                }
            }
            $oldId = (int) ($ticket['itilcategories_id'] ?? 0);
            if ($oldId !== $newCategoryId) {
                $payload['itilcategories_id'] = $newCategoryId;
                $changes[] = sprintf(
                    '%s: "%s" -> "%s"',
                    $head('itilcategories_id'),
                    $this->resolver->categoryName($oldId) ?: '(sin categoría)',
                    $newCategoryId === 0 ? '(sin categoría)' : $this->resolver->categoryName($newCategoryId),
                );
            } else {
                $newCategoryId = null; // no cambió: no interviene en el título
            }
        }

        // --- TITULO ----------------------------------------------------------
        $newTitle = $this->resolveTitle($f, $baseCols, $ticket, $sucursalHeader, $ticketId, $newCategoryId);
        if ($newTitle !== null && $this->normalizeText($newTitle) !== $this->normalizeText((string) ($ticket['name'] ?? ''))) {
            $payload['name'] = mb_substr($newTitle, 0, 255);
            $changes[]       = sprintf('%s: "%s" -> "%s"', $head('name'), (string) ($ticket['name'] ?? ''), $payload['name']);
        }

        // --- DESCRIPCION -----------------------------------------------------
        if (isset($baseCols['content']) && $this->filled($cell('content'))) {
            $raw = $cell('content');
            $new = $this->isClear($raw) ? '' : (string) ValueParser::str($raw, 65535);
            if ($this->normalizeText($new) !== $this->normalizeText((string) ($ticket['content'] ?? ''))) {
                $payload['content'] = $new;
                $changes[]          = $head('content') . ': reemplazada';
            }
        }

        // --- TIPO ------------------------------------------------------------
        if (isset($baseCols['type']) && $this->filled($cell('type'))) {
            $label = ValueParser::norm($cell('type'));
            $new   = $this->config->ticketTypes[$label] ?? null;
            if ($new === null) {
                throw new \RuntimeException("Tipo no reconocido: «{$label}».");
            }
            $old = (int) ($ticket['type'] ?? 0);
            if ($old !== $new) {
                $payload['type'] = $new;
                $changes[]       = sprintf(
                    '%s: %s -> %s',
                    $head('type'),
                    $this->config->glpiTypeLabels[$old] ?? $old,
                    $this->config->glpiTypeLabels[$new] ?? $new,
                );
            }
        }

        // --- FECHA_APERTURA --------------------------------------------------
        if (isset($baseCols['date']) && $this->filled($cell('date'))) {
            $raw = $cell('date');
            if ($this->isClear($raw)) {
                throw new \RuntimeException('La fecha de apertura no se puede vaciar: GLPI la requiere.');
            }
            $new = ValueParser::date($raw);
            if ($new === null) {
                throw new \RuntimeException('Fecha de apertura no reconocida: «' . trim((string) $raw) . '».');
            }
            $old = (string) ($ticket['date'] ?? '');
            if ($old !== $new) {
                $payload['date'] = $new;
                $changes[]       = sprintf('%s: %s -> %s', $head('date'), $old ?: '(vacía)', $new);
            }
        }

        // --- ID_EXTERNO ------------------------------------------------------
        if (isset($baseCols['externalid']) && $this->filled($cell('externalid'))) {
            $raw = $cell('externalid');
            $new = $this->isClear($raw) ? '' : (string) ValueParser::str($raw);
            $old = (string) ($ticket['externalid'] ?? '');
            if ($old !== $new) {
                $payload['externalid'] = $new;
                $changes[]             = sprintf('%s: "%s" -> "%s"', $head('externalid'), $old, $new);
            }
        }

        // --- ASIGNADO_A ------------------------------------------------------
        // Vive en glpi_tickets_users (type 2), no en la fila del ticket. GLPI no
        // tiene una entrada de "reemplaza el asignado": `_users_id_assign` AGREGA
        // un actor y deja los anteriores. Por eso aquí solo se planea el cambio y
        // reconcileAssignees() lo cierra después del PUT.
        $assignPlan = null;
        if (isset($baseCols['_users_id_assign']) && $this->filled($cell('_users_id_assign'))) {
            $raw     = $cell('_users_id_assign');
            $current = $this->currentAssignees($ticketId);
            $oldLabel = $current === []
                ? '(sin asignar)'
                : implode(', ', array_filter(array_map(fn(int $id) => $this->resolver->userName($id), $current)));

            if ($this->isClear($raw)) {
                if ($current !== []) {
                    $assignPlan = ['desired' => null, 'before' => $current, 'clear' => true];
                    $changes[]  = sprintf('%s: "%s" -> "(sin asignar)"', $head('_users_id_assign'), $oldLabel);
                }
            } else {
                $new = $this->resolver->userId(ValueParser::str($raw));
                if ($new === 0) {
                    throw new \RuntimeException('Técnico no encontrado en GLPI: «' . trim((string) $raw) . '».');
                }
                // Distinto de "ya lo tiene": si además está asignado a alguien más,
                // reasignar significa que ese otro debe salir.
                if ($current !== [$new]) {
                    $payload['_users_id_assign'] = $new;
                    $assignPlan = ['desired' => $new, 'before' => $current, 'clear' => false];
                    $changes[]  = sprintf(
                        '%s: "%s" -> "%s"',
                        $head('_users_id_assign'),
                        $oldLabel,
                        $this->resolver->userName($new),
                    );
                }
            }
        }

        return [$payload, $changes, $assignPlan];
    }

    /**
     * Cierra el reemplazo del técnico DESPUÉS del PUT, quitando a los que sobran.
     *
     * Solo quita a quienes ya estaban asignados ANTES de escribir, y solo si el
     * técnico pedido quedó efectivamente puesto. Esto es deliberado: las reglas de
     * negocio de GLPI pueden asignar a alguien durante el propio PUT (por ejemplo
     * "categoría X -> técnico Y"), y Nexus no debe deshacer en silencio lo que la
     * regla acaba de decidir. Si el técnico pedido no quedó, no se toca nada y la
     * verificación reporta DESVIACION diciendo quién quedó en su lugar.
     */
    private function reconcileAssignees(GlpiConnector $connector, string $token, int $ticketId, array $plan): void
    {
        $now = $this->currentAssignees($ticketId);

        if ($plan['clear']) {
            $remove = $now;                                   // se pidió dejarlo sin asignar
        } elseif ($plan['desired'] !== null && in_array($plan['desired'], $now, true)) {
            $remove = array_values(array_intersect(
                array_diff($plan['before'], [$plan['desired']]),
                $now,
            ));
        } else {
            return;                                           // no se puso: no se quita nada
        }

        if ($remove === []) {
            return;
        }

        foreach ($this->assigneeRows($ticketId) as $relationId => $userId) {
            if (! in_array($userId, $remove, true)) {
                continue;
            }
            $res = $connector->removeTicketActor($relationId, $token);
            if (! $res->success) {
                $this->log('WARN', "#{$ticketId}: no se pudo quitar al técnico anterior ("
                    . ($this->resolver->userName($userId) ?: $userId) . '): ' . $res->message);
            }
        }
    }

    /** @return array<int,int> id de la relación => users_id (actores tipo 2). */
    private function assigneeRows(int $ticketId): array
    {
        $db = $this->glpiDb->connection();
        if (! $db->tableExists('glpi_tickets_users')) {
            return [];
        }
        $out = [];
        foreach ($db->table('glpi_tickets_users')->select('id, users_id')
            ->where('tickets_id', $ticketId)->where('type', 2)->get()->getResultArray() as $row) {
            $out[(int) $row['id']] = (int) $row['users_id'];
        }

        return $out;
    }

    /**
     * Homologated title, or null when the title must be left alone.
     *
     * Default: se rearma SOLO si la fila trae TITULO (usando la categoría y la
     * sucursal de la fila y, si vienen vacías, las del ticket vivo). Con el
     * toggle del SuperAdmin encendido también se rearma cuando cambió la
     * categoría, intercambiando de forma determinista el prefijo CLIENTE del
     * título actual; si ese prefijo no se reconoce, no se toca nada.
     */
    private function resolveTitle(array $f, array $baseCols, array $ticket, ?string $sucursalHeader, int $ticketId, ?int $newCategoryId): ?string
    {
        $titleHeader = $baseCols['name']['header'] ?? null;
        $titleCell   = $titleHeader !== null ? ($f[$titleHeader] ?? null) : null;
        $currentName = (string) ($ticket['name'] ?? '');

        $categoryId = $newCategoryId ?? (int) ($ticket['itilcategories_id'] ?? 0);
        $cliente    = $this->resolver->clienteForCategoryId($categoryId);

        if ($this->filled($titleCell)) {
            if ($this->isClear($titleCell)) {
                throw new \RuntimeException('El título no se puede vaciar: GLPI lo requiere.');
            }
            $sucursal = '';
            if ($sucursalHeader !== null) {
                $sucursal = $this->filled($f[$sucursalHeader] ?? null)
                    ? (string) ValueParser::str($f[$sucursalHeader])
                    : $this->currentSucursal($ticketId);
            }
            $parts = array_values(array_filter([$cliente, $sucursal, (string) ValueParser::str($titleCell)], static fn($p) => $p !== '' && $p !== null));

            return $parts === [] ? null : mb_strtoupper(implode(' - ', $parts), 'UTF-8');
        }

        if (! $this->rehomologate || $newCategoryId === null) {
            return null;
        }

        // Intercambio determinista del prefijo: solo si el título actual empieza
        // exactamente con el CLIENTE de la categoría anterior.
        $oldCliente = $this->resolver->clienteForCategoryId((int) ($ticket['itilcategories_id'] ?? 0));
        if ($oldCliente === '' || $cliente === '') {
            return null;
        }
        $prefix = mb_strtoupper($oldCliente) . ' - ';
        if (mb_stripos($currentName, $prefix) !== 0) {
            $this->log('WARN', "#{$ticketId}: no se rehomologó el título, no empieza con «{$oldCliente} - ».");
            return null;
        }

        return mb_strtoupper($cliente . ' - ' . mb_substr($currentName, mb_strlen($prefix)), 'UTF-8');
    }

    // ------------------------------------------------------------------
    // Contenedores del plugin
    // ------------------------------------------------------------------

    /**
     * Diffs the filled plugin cells against the ticket's existing container row.
     *
     * @return array{set:array<string,mixed>,changes:string[],rowId:?int}
     */
    private function planPluginRow(int $containerId, array $planCols, array $f, int $ticketId): array
    {
        $empty = ['set' => [], 'changes' => [], 'rowId' => null];

        $meta = $this->introspector->container($containerId);
        if ($meta === null) {
            return $empty;
        }

        $existing = $this->existingPluginRow($meta['dataTable'], $containerId, $ticketId);

        $set     = [];
        $changes = [];

        foreach ($planCols as $col) {
            $value = $f[$col['header']] ?? null;
            if (! $this->filled($value)) {
                continue;
            }
            $clear = $this->isClear($value);

            if ($col['type'] === 'dropdown') {
                $new = $clear ? 0 : $this->resolver->dropdownId($col['dropdownTable'], ValueParser::str($value), $this->autocreate);
                if (! $clear && $new === 0) {
                    throw new \RuntimeException(
                        $col['header'] . ': el valor «' . trim((string) $value) . '» no existe en el catálogo de GLPI'
                        . ' y la autocreación está deshabilitada.'
                    );
                }
                $old = (int) ($existing[$col['column']] ?? 0);
                if ($old === $new) {
                    continue;
                }
                $set[$col['column']] = $new;
                $changes[] = sprintf(
                    '%s: "%s" -> "%s"',
                    $col['header'],
                    $this->resolver->dropdownName($col['dropdownTable'], $old) ?: '(vacío)',
                    $clear ? '(vacío)' : trim((string) $value),
                );
                continue;
            }

            if ($col['type'] === 'date') {
                $new = $clear ? null : ValueParser::date($value);
                if (! $clear && $new === null) {
                    throw new \RuntimeException($col['header'] . ': fecha no reconocida «' . trim((string) $value) . '».');
                }
            } elseif ($col['type'] === 'number') {
                $new = $clear ? null : trim((string) $value);
            } else {
                $new = $clear ? '' : ValueParser::str($value, $col['type'] === 'textarea' ? 65535 : 255);
            }

            $old = $existing[$col['column']] ?? null;
            if ((string) ($old ?? '') === (string) ($new ?? '')) {
                continue;
            }
            $set[$col['column']] = $new;
            $changes[] = sprintf(
                '%s: "%s" -> "%s"',
                $col['header'],
                (string) ($old ?? '') === '' ? '(vacío)' : (string) $old,
                (string) ($new ?? '') === '' ? '(vacío)' : (string) $new,
            );
        }

        return ['set' => $set, 'changes' => $changes, 'rowId' => isset($existing['id']) ? (int) $existing['id'] : null];
    }

    /**
     * Upsert: the importer always INSERTs because the ticket is brand new; here
     * the row usually already exists and only some columns must change.
     */
    private function writePluginRow(int $containerId, array $planned, int $ticketId): void
    {
        $meta = $this->introspector->container($containerId);
        if ($meta === null) {
            return;
        }
        $db = $this->glpiDb->connection();

        if ($planned['rowId'] !== null) {
            $db->table($meta['dataTable'])->where('id', $planned['rowId'])->update($planned['set']);
            return;
        }

        $db->table($meta['dataTable'])->insert($planned['set'] + [
            'items_id'                    => $ticketId,
            'itemtype'                    => 'Ticket',
            'plugin_fields_containers_id' => $containerId,
            'entities_id'                 => $this->entities,
        ]);
    }

    /**
     * The container row for a ticket, or null. The plugin does not guarantee a
     * unique index on items_id, so duplicates are reported and the oldest wins.
     */
    private function existingPluginRow(string $dataTable, int $containerId, int $ticketId): ?array
    {
        $db = $this->glpiDb->connection();
        if (! $db->tableExists($dataTable)) {
            return null;
        }

        $rows = $db->table($dataTable)
            ->where('items_id', $ticketId)
            ->where('itemtype', 'Ticket')
            ->where('plugin_fields_containers_id', $containerId)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        if (count($rows) > 1) {
            $this->log('WARN', "#{$ticketId}: {$dataTable} tiene " . count($rows) . ' filas para el mismo contenedor; se usa la más antigua.');
        }

        return $rows[0] ?? null;
    }

    // ------------------------------------------------------------------
    // Cierre
    // ------------------------------------------------------------------

    /**
     * Registers the GLPI solution unless the ticket already has one and the row
     * did not supply its own text. Reabrir un ticket ya resuelto para corregirle
     * la categoría no debe ensuciarlo con una segunda solución automática.
     *
     * @return bool true when a solution was actually created
     */
    private function ensureSolution(GlpiConnector $connector, string $token, int $ticketId, array $f): bool
    {
        $rowText  = $this->filled($f[$this->config->solutionHeader] ?? null)
            ? (string) ValueParser::str($f[$this->config->solutionHeader], 65535)
            : null;

        if ($rowText === null && $this->ticketHasSolution($ticketId)) {
            return false;
        }

        $res = $connector->addSolution($ticketId, $rowText ?? $this->solutionText, $this->authorUserId, $token);
        if (! $res->success) {
            throw new \RuntimeException('No se pudo registrar la solución: ' . $res->message);
        }

        return true;
    }

    /** closedate tal como quedó en GLPI, para contrastarlo con el del Excel. */
    private function currentCloseDate(int $ticketId): ?string
    {
        $row = $this->glpiDb->connection()->table('glpi_tickets')
            ->select('closedate')->where('id', $ticketId)->get()->getRowArray();

        return isset($row['closedate']) && $row['closedate'] !== '' ? (string) $row['closedate'] : null;
    }

    private function ticketHasSolution(int $ticketId): bool
    {
        $db = $this->glpiDb->connection();
        if (! $db->tableExists('glpi_itilsolutions')) {
            return false;
        }

        return $db->table('glpi_itilsolutions')
            ->where('items_id', $ticketId)
            ->where('itemtype', 'Ticket')
            ->countAllResults() > 0;
    }

    /**
     * Close date, in order of preference: the row's cell, the date the ticket
     * already carried, and finally "ahora".
     *
     * `explicit` distingue las dos primeras (una fecha que alguien decidió y que
     * por tanto hay que preservar) de la última (un relleno), para no reescribir
     * en la BD un sello que solo difiere en segundos.
     *
     * @return array{date:string,explicit:bool}
     */
    private function closeDateFor(array $f, array $baseCols, array $ticket): array
    {
        $header = $baseCols['closedate']['header'] ?? null;
        if ($header !== null && $this->filled($f[$header] ?? null) && ! $this->isClear($f[$header])) {
            $parsed = ValueParser::date($f[$header]);
            if ($parsed === null) {
                throw new \RuntimeException('Fecha de cierre no reconocida: «' . trim((string) $f[$header]) . '».');
            }
            return ['date' => $parsed, 'explicit' => true];
        }

        foreach (['closedate', 'solvedate'] as $key) {
            if (! empty($ticket[$key])) {
                return ['date' => (string) $ticket[$key], 'explicit' => true];
            }
        }

        return ['date' => date('Y-m-d H:i:s'), 'explicit' => false];
    }

    /**
     * Fija solvedate/closedate escribiendo directo en glpi_tickets.
     *
     * Necesario porque GLPI SELLA `closedate` con el momento del cierre y descarta
     * la fecha enviada: verificado en un solo PUT, en dos PUT separados y con el
     * ticket abierto. Sin esto, cerrar en masa tickets viejos los marcaría a todos
     * como cerrados el día de la carga y los reportes de cierre saldrían mal.
     *
     * El cierre en sí (solución, estatus, historial y reglas de negocio) sigue
     * pasando por la API; esto solo corrige esa columna, con el mismo criterio con
     * el que ya se escriben las filas del contenedor del plugin.
     */
    private function stampDates(int $ticketId, ?string $solvedate, ?string $closedate): void
    {
        $set = [];
        if ($solvedate !== null && $solvedate !== '') {
            $set['solvedate'] = $solvedate;
        }
        if ($closedate !== null && $closedate !== '') {
            $set['closedate'] = $closedate;
        }
        if ($set === []) {
            return;
        }

        $db = $this->glpiDb->connection();
        $db->table('glpi_tickets')->where('id', $ticketId)->update($set);

        $after = $this->currentCloseDate($ticketId);
        if (isset($set['closedate']) && $after !== $set['closedate']) {
            $this->log('WARN', "#{$ticketId}: no se pudo fijar la fecha de cierre en «{$set['closedate']}» (quedó «{$after}»).");
        }
    }

    // ------------------------------------------------------------------
    // Verificación
    // ------------------------------------------------------------------

    /**
     * Re-reads the ticket and confirms every value actually stuck. GLPI runs its
     * business rules on update too, so a category can be rewritten server-side;
     * without this the report would claim ACTUALIZADO on a ticket that never
     * changed.
     *
     * @return string[] human descriptions of what did not stick
     */
    private function verify(GlpiConnector $connector, string $token, int $ticketId, array $payload, array $pluginPlans, ?int $expectedStatus, ?array $assignPlan = null): array
    {
        $deviations = [];

        $read = $connector->getTicket($ticketId, $token);
        if (! $read->success) {
            return ['No se pudo releer el ticket para verificar: ' . $read->message];
        }
        $after = (array) $read->payload;

        // `content` queda fuera a propósito: GLPI lo reescribe (HTML, saltos de
        // línea) y compararlo produciría falsos positivos constantes.
        foreach (['itilcategories_id', 'type', 'date', 'externalid', 'name'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $sent = $payload[$key];
            $got  = $after[$key] ?? null;
            $same = in_array($key, ['itilcategories_id', 'type'], true)
                ? (int) $sent === (int) $got
                : $this->normalizeText((string) $sent) === $this->normalizeText((string) $got);
            if (! $same) {
                $hint = $key === 'date'
                    ? '. GLPI no acepta una fecha de apertura posterior a la fecha límite del SLA del ticket'
                    . ' (time_to_resolve); revisa el SLA o mueve la fecha antes de ese límite'
                    : '';
                $deviations[] = "{$key}: se envió «{$sent}» y GLPI dejó «" . (string) $got . '»' . $hint;
            }
        }

        if ($expectedStatus !== null && (int) ($after['status'] ?? 0) !== $expectedStatus) {
            $deviations[] = sprintf(
                'estatus: se esperaba «%s» y quedó «%s»',
                $this->config->glpiStatusLabels[$expectedStatus] ?? $expectedStatus,
                $this->config->glpiStatusLabels[(int) ($after['status'] ?? 0)] ?? ($after['status'] ?? '?'),
            );
        }

        // Técnico: el caso típico de desviación. Una regla de negocio de GLPI del
        // tipo "categoría X -> técnico Y" pisa el valor del Excel durante el mismo
        // PUT, así que el mensaje dice quién quedó, no solo que falló.
        if ($assignPlan !== null) {
            $now       = $this->currentAssignees($ticketId);
            $nowLabels = implode(', ', array_filter(array_map(fn(int $id) => $this->resolver->userName($id) ?: "id{$id}", $now)));

            if ($assignPlan['clear'] && $now !== []) {
                $deviations[] = 'técnico asignado: se pidió dejarlo sin asignar y GLPI dejó «' . $nowLabels . '»';
            } elseif (! $assignPlan['clear'] && ! in_array((int) $assignPlan['desired'], $now, true)) {
                $deviations[] = sprintf(
                    'técnico asignado: se pidió «%s» y el ticket quedó con «%s». Casi siempre es una regla de '
                    . 'negocio de GLPI que reasigna por categoría (Administración > Reglas > Reglas de tickets).',
                    $this->resolver->userName((int) $assignPlan['desired']) ?: $assignPlan['desired'],
                    $nowLabels !== '' ? $nowLabels : '(sin asignar)',
                );
            }
        }

        foreach ($pluginPlans as $containerId => $planned) {
            $meta = $this->introspector->container($containerId);
            if ($meta === null) {
                continue;
            }
            $row = $this->existingPluginRow($meta['dataTable'], $containerId, $ticketId);
            foreach ($planned['set'] as $column => $sent) {
                if ((string) ($row[$column] ?? '') !== (string) ($sent ?? '')) {
                    $deviations[] = "{$meta['label']}.{$column}: no se guardó el valor";
                }
            }
        }

        return $deviations;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * A cell counts as filled when ValueParser would read a value out of it.
     * N/A, NAN and NONE therefore mean "no tocar", exactly like an empty cell
     * (same contract as the importer; documented in the template).
     */
    private function filled(mixed $value): bool
    {
        return ValueParser::str($value, 65535) !== null;
    }

    private function isClear(mixed $value): bool
    {
        return mb_strtoupper(trim((string) ($value ?? ''))) === mb_strtoupper($this->config->clearSentinel);
    }

    /** Comparación tolerante a HTML/entidades/espacios que mete GLPI. */
    private function normalizeText(string $value): string
    {
        $plain = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    }

    /** @return int[] GLPI user ids currently assigned (actor type 2). */
    private function currentAssignees(int $ticketId): array
    {
        $db = $this->glpiDb->connection();
        if (! $db->tableExists('glpi_tickets_users')) {
            return [];
        }
        $rows = $db->table('glpi_tickets_users')
            ->select('users_id')
            ->where('tickets_id', $ticketId)
            ->where('type', 2)
            ->get()->getResultArray();

        return array_map(static fn($r) => (int) $r['users_id'], $rows);
    }

    /** Valor actual del campo sucursal, para rearmar el título homologado. */
    private function currentSucursal(int $ticketId): string
    {
        foreach ($this->introspector->containers() as $container) {
            foreach ($container['fields'] as $field) {
                if ($field['name'] !== $this->config->sucursalFieldName) {
                    continue;
                }
                $row = $this->existingPluginRow($container['dataTable'], (int) $container['id'], $ticketId);
                if ($row === null) {
                    return '';
                }
                if ($field['type'] === 'dropdown') {
                    return $this->resolver->dropdownName($field['dropdownTable'], (int) ($row[$field['column']] ?? 0));
                }
                return (string) ($row[$field['column']] ?? '');
            }
        }

        return '';
    }

    /** @return int[] GLPI status ids considered closed by the module config. */
    private function closedStatusIds(): array
    {
        return array_values(array_intersect_key(
            $this->config->ticketStatuses,
            array_flip($this->config->closedStatuses),
        ));
    }

    /**
     * Author of the solution: the GLPI user mapped to the Nexus operator who
     * uploaded the file. 0 leaves it to the API session user.
     */
    private function resolveAuthor(array $job): int
    {
        $uploadedBy = (int) ($job['uploaded_by'] ?? 0);
        if ($uploadedBy > 0) {
            $user = model(UserModel::class)->find($uploadedBy);
            return (int) ($user['glpi_user_id'] ?? 0);
        }

        return 0;
    }

    /**
     * Appends an output column to the sheet if it is not already a header.
     *
     * @param array<string,int> $headerIdx by reference: gains the new column
     * @param int               $nextCol   by reference: next free 0-based index
     */
    private function ensureColumn(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array &$headerIdx, string $header, int &$nextCol): int
    {
        if (isset($headerIdx[$header])) {
            return $headerIdx[$header];
        }
        $sheet->setCellValue([$nextCol + 1, 1], $header);
        $headerIdx[$header] = $nextCol;

        return $nextCol++;
    }

    /**
     * @return array{0:array<string,array>,1:array<int,array<int,array>>}
     */
    private function splitColumns(array $columns): array
    {
        $base   = [];
        $plugin = [];
        foreach ($columns as $col) {
            if ($col['kind'] === 'base') {
                $base[$col['glpiKey']] = $col;
            } elseif ($col['kind'] === 'plugin') {
                $plugin[$col['containerId']][] = $col;
            }
        }

        return [$base, $plugin];
    }

    private function findSucursalHeader(array $columns): ?string
    {
        foreach ($columns as $col) {
            if (($col['kind'] ?? '') === 'plugin' && ($col['field'] ?? '') === $this->config->sucursalFieldName) {
                return $col['header'];
            }
        }

        return null;
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
        log_message('info', '[ServiceDesk][update] ' . trim($line));
    }
}
