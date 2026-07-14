<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Validates an uploaded import Excel BEFORE it is enqueued. Mirrors the exact
 * plan the importer will use, so a file that validates here will import cleanly.
 *
 * Returns ServiceResult; on success data carries: containers, totalRows,
 * warnings. On failure, errors is the human list and data.rowErrors the
 * structured per-cell issues.
 */
class TicketImportValidator
{
    private const SHEET = 'DATOS';

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
        private ServiceDeskSettings $settings,
    ) {}

    public function validateFile(string $path): ServiceResult
    {
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

        $rowErrors = [];
        $warnings  = [];
        $dataCount = 0;

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

            foreach ($columns as $col) {
                if ($col['kind'] === 'output') {
                    continue;
                }
                $idx   = $headerIdx[$col['header']];
                $value = $rowArr[$idx] ?? null;
                $empty = ValueParser::isEmpty($value);

                // Required.
                if (! empty($col['required']) && $empty) {
                    $rowErrors[] = ['row' => $excelRow, 'column' => $col['header'], 'message' => 'Campo requerido vacío.'];
                    continue;
                }
                if ($empty) {
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
                            $warnings[] = "Fila {$excelRow} · {$col['header']}: «" . trim((string) $value) . '» no existe, se creará al importar.';
                        } else {
                            $rowErrors[] = ['row' => $excelRow, 'column' => $col['header'], 'message' => 'Valor no válido: «' . trim((string) $value) . '».'];
                        }
                    }
                }
            }

            // Cross-field: a closed status needs a close date.
            $this->validateCloseDate($columns, $headerIdx, $rowArr, $excelRow, $rowErrors);
        }

        if ($dataCount === 0) {
            return ServiceResult::fail('El Excel no tiene filas de datos para importar.');
        }
        if ($maxRows > 0 && $dataCount > $maxRows) {
            return ServiceResult::fail(
                "El archivo tiene {$dataCount} tickets y el máximo permitido es {$maxRows}. "
                . 'Divide el archivo o pide al administrador aumentar el límite.'
            );
        }

        if ($rowErrors !== []) {
            $preview = array_slice(array_map(
                static fn($e) => "Fila {$e['row']} · {$e['column']}: {$e['message']}",
                $rowErrors,
            ), 0, 50);
            return ServiceResult::fail(
                $preview,
                ['containers' => $containers, 'totalRows' => $dataCount, 'rowErrors' => $rowErrors, 'warnings' => $warnings],
            );
        }

        return ServiceResult::ok(
            ['containers' => $containers, 'totalRows' => $dataCount, 'warnings' => $warnings],
            "Validación correcta: {$dataCount} tickets listos para importar.",
        );
    }

    private function validateCloseDate(array $columns, array $headerIdx, array $rowArr, int $excelRow, array &$rowErrors): void
    {
        $statusHeader = $this->headerForKey($columns, 'status');
        $closeHeader  = $this->headerForKey($columns, 'closedate');
        if ($statusHeader === null || $closeHeader === null) {
            return;
        }
        $status = ValueParser::norm($rowArr[$headerIdx[$statusHeader]] ?? '');
        $config = config('App\Modules\ServiceDesk\Config\ServiceDesk');
        if (in_array($status, $config->closedStatuses, true)) {
            if (ValueParser::isEmpty($rowArr[$headerIdx[$closeHeader]] ?? null)) {
                $rowErrors[] = ['row' => $excelRow, 'column' => $closeHeader, 'message' => 'Requerida cuando el estatus es ' . $status . '.'];
            }
        }
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
