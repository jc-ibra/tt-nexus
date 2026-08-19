<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Validates an uploaded Excel BEFORE it is enqueued. Mirrors the exact plan the
 * engine will use, so a file that validates here runs cleanly.
 *
 * Two modes, one set of cell rules (dates, numbers, catalogs are checked the
 * same way in both, on purpose):
 *
 *   MODE_CREATE - alta masiva. Every required column must be filled; a row that
 *                 already carries TICKET_ID is a resume-skip, not an error.
 *   MODE_UPDATE - plancha + cierre. TICKET_ID is the ONLY required column and
 *                 must point at a ticket that exists in GLPI; every other
 *                 `required` is relaxed because an empty cell means "no tocar".
 *
 * Returns ServiceResult; on success data carries: containers, totalRows,
 * warnings, fillCounts (and in update mode ticketIds/closedIds). On failure,
 * errors is the human list and data.rowErrors the structured per-cell issues.
 */
class TicketImportValidator
{
    public const MODE_CREATE = 'create';
    public const MODE_UPDATE = 'update';

    private const SHEET = 'DATOS';

    /** GLPI ids are looked up in chunks so a 500-row file is a couple of queries. */
    private const ID_CHUNK = 500;

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
        private ServiceDeskSettings $settings,
        private GlpiDbConnection $glpiDb,
        private ServiceDeskConfig $config,
    ) {}

    public function validateFile(string $path, string $mode = self::MODE_CREATE): ServiceResult
    {
        $isUpdate = $mode === self::MODE_UPDATE;

        if (! is_file($path)) {
            return ServiceResult::fail('No se encontró el archivo cargado.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return ServiceResult::fail('No se pudo leer el Excel: ' . $e->getMessage());
        }

        $containers = TicketTemplateBuilder::readMetaContainers($spreadsheet);
        if ($containers === []) {
            return ServiceResult::fail(
                'El archivo no tiene metadatos de contenedor. Descarga el template desde Service Desk y no borres sus hojas ocultas.'
            );
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET);
        if ($sheet === null) {
            return ServiceResult::fail("El Excel no tiene la hoja «" . self::SHEET . "».");
        }

        $plan    = $this->introspector->buildPlan($containers, true);
        $columns = $plan['columns'];

        // formatData=false: real date cells arrive as Excel serials (parsed
        // unambiguously by ValueParser::date), never as locale-formatted strings.
        $rows = $sheet->toArray(null, true, false, false);
        if (count($rows) < 1) {
            return ServiceResult::fail('El Excel está vacío.');
        }

        // Header map: header => 0-based column index.
        $headerRow = array_map(static fn($v) => trim((string) ($v ?? '')), $rows[0]);
        $headerIdx = [];
        foreach ($headerRow as $i => $name) {
            if ($name !== '') {
                $headerIdx[$name] = $i;
            }
        }

        // 1. Structural: every non-output plan column must be a header.
        $missing = [];
        foreach ($columns as $col) {
            if ($col['kind'] === 'output') {
                continue;
            }
            if (! isset($headerIdx[$col['header']])) {
                $missing[] = $col['header'];
            }
        }
        if ($missing !== []) {
            return ServiceResult::fail(
                'Faltan columnas requeridas en el Excel: ' . implode(', ', $missing)
                . '. Usa el template descargado sin modificar los encabezados.'
            );
        }

        // In update mode TICKET_ID stops being an output column and becomes the key.
        $idHeader = $this->config->ticketIdHeader;
        $idCol    = $headerIdx[$idHeader] ?? null;
        if ($isUpdate && $idCol === null) {
            return ServiceResult::fail(
                "El Excel no tiene la columna «{$idHeader}». En modo actualización esa columna es la que identifica"
                . ' qué ticket se corrige: usa el archivo de resultado del importador o el template descargado.'
            );
        }

        // Precompute uppercased option sets for matching.
        $optionSets = [];
        foreach ($columns as $col) {
            if (! empty($col['options'])) {
                $optionSets[$col['header']] = array_flip(array_map(
                    static fn($o) => mb_strtoupper(trim((string) $o)),
                    $col['options'],
                ));
            }
        }

        $autocreate = $this->settings->autocreateCatalogValues();
        $maxRows    = $this->settings->importMaxRows();
        $sentinel   = mb_strtoupper($this->config->clearSentinel);

        $rowErrors  = [];
        $warnings   = [];
        $fillCounts = [];
        $ticketIds  = [];   // excelRow => ticketId (update mode)
        $seenIds    = [];   // ticketId => first excelRow, for duplicate detection
        $dataCount  = 0;

        // Data rows start at Excel row 2 (index 1).
        for ($r = 1; $r < count($rows); $r++) {
            $excelRow = $r + 1;
            $rowArr   = $rows[$r];

            // Skip fully empty rows.
            $hasContent = false;
            foreach ($rowArr as $v) {
                if (trim((string) ($v ?? '')) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (! $hasContent) {
                continue;
            }
            $dataCount++;

            // --- update mode: TICKET_ID is the key of the row ---------------
            if ($isUpdate) {
                $rawId = trim((string) ($rowArr[$idCol] ?? ''));
                if ($rawId === '' || ! ctype_digit(ltrim($rawId, '0')) || (int) $rawId <= 0) {
                    $rowErrors[] = [
                        'row'     => $excelRow,
                        'column'  => $idHeader,
                        'message' => $rawId === ''
                            ? 'Requerido: este flujo no crea tickets, solo actualiza los que ya existen.'
                            : 'Debe ser el id numérico del ticket en GLPI, no «' . $rawId . '».',
                    ];
                } else {
                    $ticketId = (int) $rawId;
                    if (isset($seenIds[$ticketId])) {
                        $rowErrors[] = [
                            'row'     => $excelRow,
                            'column'  => $idHeader,
                            'message' => "El ticket {$ticketId} ya venía en la fila {$seenIds[$ticketId]}. "
                                . 'Deja una sola fila por ticket para no aplicar cambios contradictorios.',
                        ];
                    } else {
                        $seenIds[$ticketId]   = $excelRow;
                        $ticketIds[$excelRow] = $ticketId;
                    }
                }
            }

            // --- per-column cell rules -------------------------------------
            $filledInRow = 0;
            foreach ($columns as $col) {
                if ($col['kind'] === 'output') {
                    continue;
                }
                $idx   = $headerIdx[$col['header']];
                $value = $rowArr[$idx] ?? null;
                $empty = ValueParser::isEmpty($value);

                if (! $empty) {
                    $filledInRow++;
                    $fillCounts[$col['header']] = ($fillCounts[$col['header']] ?? 0) + 1;
                }

                // Required. Relaxed in update mode: empty means "no tocar".
                if (! $isUpdate && ! empty($col['required']) && $empty) {
                    $rowErrors[] = ['row' => $excelRow, 'column' => $col['header'], 'message' => 'Campo requerido vacío.'];
                    continue;
                }
                if ($empty) {
                    continue;
                }

                // The clear sentinel is valid wherever a value is valid: it means
                // "borra este campo", so it must bypass every type/option check.
                if ($isUpdate && mb_strtoupper(trim((string) $value)) === $sentinel) {
                    continue;
                }

                // Date parseability.
                if ($col['type'] === 'date' && ValueParser::date($value) === null) {
                    $rowErrors[] = ['row' => $excelRow, 'column' => $col['header'], 'message' => 'Fecha no reconocida: «' . trim((string) $value) . '».'];
                    continue;
                }

                // Number.
                if ($col['type'] === 'number' && ! is_numeric(trim((string) $value))) {
                    $rowErrors[] = ['row' => $excelRow, 'column' => $col['header'], 'message' => 'Se esperaba un número.'];
                    continue;
                }

                // List-backed columns.
                if (isset($optionSets[$col['header']])) {
                    $needle = mb_strtoupper(trim((string) $value));
                    if (! isset($optionSets[$col['header']][$needle])) {
                        // enum / category / user must always match; dropdowns may
                        // be auto-created when the admin enabled it.
                        if ($col['type'] === 'dropdown' && $autocreate) {
                            $warnings[] = "Fila {$excelRow} · {$col['header']}: «" . trim((string) $value) . '» no existe, se creará al aplicar.';
                        } else {
                            $rowErrors[] = ['row' => $excelRow, 'column' => $col['header'], 'message' => 'Valor no válido: «' . trim((string) $value) . '».'];
                        }
                    }
                }
            }

            // Cross-field: a closed status needs a close date.
            $this->validateCloseDate($columns, $headerIdx, $rowArr, $excelRow, $rowErrors, $warnings, $isUpdate);

            // A row that carries only its key applies nothing; worth flagging so
            // the operator does not read "SIN CAMBIOS" later as a malfunction.
            if ($isUpdate && $filledInRow === 0) {
                $warnings[] = "Fila {$excelRow}: solo trae {$idHeader}, no hay ningún dato que planchar.";
            }
        }

        if ($dataCount === 0) {
            return ServiceResult::fail(
                $isUpdate ? 'El Excel no tiene filas para actualizar.' : 'El Excel no tiene filas de datos para importar.'
            );
        }
        if ($maxRows > 0 && $dataCount > $maxRows) {
            return ServiceResult::fail(
                "El archivo tiene {$dataCount} tickets y el máximo permitido es {$maxRows}. "
                . 'Divide el archivo o pide al administrador aumentar el límite.'
            );
        }

        // --- update mode: the ids must exist in GLPI ------------------------
        $closedIds = [];
        if ($isUpdate && $ticketIds !== []) {
            $check = $this->checkTicketsExist($ticketIds);
            foreach ($check['missing'] as $excelRow => $ticketId) {
                $rowErrors[] = [
                    'row'     => $excelRow,
                    'column'  => $idHeader,
                    'message' => "El ticket {$ticketId} no existe en GLPI (o está en la papelera).",
                ];
            }
            $closedIds = $check['closed'];
            if ($closedIds !== []) {
                $warnings[] = count($closedIds) . ' ticket(s) ya están resueltos o cerrados: '
                    . 'se reabrirán para escribirles y se volverán a cerrar conservando su fecha de cierre.';
            }
        }

        if ($rowErrors !== []) {
            $preview = array_slice(array_map(
                static fn($e) => "Fila {$e['row']} · {$e['column']}: {$e['message']}",
                $rowErrors,
            ), 0, 50);
            return ServiceResult::fail(
                $preview,
                [
                    'containers' => $containers,
                    'totalRows'  => $dataCount,
                    'rowErrors'  => $rowErrors,
                    'warnings'   => $warnings,
                    'fillCounts' => $fillCounts,
                ],
            );
        }

        $data = [
            'containers' => $containers,
            'totalRows'  => $dataCount,
            'warnings'   => $warnings,
            'fillCounts' => $fillCounts,
        ];
        if ($isUpdate) {
            $data['ticketIds'] = array_values($ticketIds);
            $data['closedIds'] = $closedIds;
        }

        return ServiceResult::ok(
            $data,
            $isUpdate
                ? "Validación correcta: {$dataCount} ticket(s) listos para actualizar."
                : "Validación correcta: {$dataCount} tickets listos para importar.",
        );
    }

    /**
     * Looks the row ids up in the live GLPI DB (cheap, no API session).
     *
     * @param array<int,int> $ticketIds excelRow => ticketId
     * @return array{missing:array<int,int>,closed:array<int,int>} both keyed excelRow => ticketId
     */
    private function checkTicketsExist(array $ticketIds): array
    {
        $missing = [];
        $closed  = [];

        $db = $this->glpiDb->connection();
        if (! $db->tableExists('glpi_tickets')) {
            // Sin BD de GLPI no se puede afirmar que falten: no se inventan errores.
            return ['missing' => [], 'closed' => []];
        }

        $found  = [];
        $unique = array_values(array_unique($ticketIds));
        foreach (array_chunk($unique, self::ID_CHUNK) as $chunk) {
            $rows = $db->table('glpi_tickets')
                ->select('id, status, is_deleted')
                ->whereIn('id', $chunk)
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $found[(int) $row['id']] = $row;
            }
        }

        $closedStatusIds = array_values(array_intersect_key(
            $this->config->ticketStatuses,
            array_flip($this->config->closedStatuses),
        ));

        foreach ($ticketIds as $excelRow => $ticketId) {
            $row = $found[$ticketId] ?? null;
            if ($row === null || (int) ($row['is_deleted'] ?? 0) === 1) {
                $missing[$excelRow] = $ticketId;
                continue;
            }
            if (in_array((int) $row['status'], $closedStatusIds, true)) {
                $closed[$excelRow] = $ticketId;
            }
        }

        return ['missing' => $missing, 'closed' => $closed];
    }

    /**
     * A closed status needs a close date. Hard error on create (the ticket is
     * born closed and the date is the only evidence); warning on update, where
     * the missing date falls back to "ahora" instead of blocking the batch.
     */
    private function validateCloseDate(
        array $columns,
        array $headerIdx,
        array $rowArr,
        int $excelRow,
        array &$rowErrors,
        array &$warnings,
        bool $isUpdate,
    ): void {
        $statusHeader = $this->headerForKey($columns, 'status');
        $closeHeader  = $this->headerForKey($columns, 'closedate');
        if ($statusHeader === null || $closeHeader === null) {
            return;
        }
        $status = ValueParser::norm($rowArr[$headerIdx[$statusHeader]] ?? '');
        if (! in_array($status, $this->config->closedStatuses, true)) {
            return;
        }
        if (! ValueParser::isEmpty($rowArr[$headerIdx[$closeHeader]] ?? null)) {
            return;
        }

        if ($isUpdate) {
            $warnings[] = "Fila {$excelRow} · {$closeHeader}: vacía con estatus {$status}; "
                . 'se conservará la fecha de cierre que ya tenga el ticket, o se usará la fecha de aplicación.';
            return;
        }
        $rowErrors[] = ['row' => $excelRow, 'column' => $closeHeader, 'message' => 'Requerida cuando el estatus es ' . $status . '.'];
    }

    private function headerForKey(array $columns, string $glpiKey): ?string
    {
        foreach ($columns as $col) {
            if (($col['glpiKey'] ?? null) === $glpiKey) {
                return $col['header'];
            }
        }
        return null;
    }
}
