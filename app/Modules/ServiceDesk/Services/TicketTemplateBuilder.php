<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Builds the .xlsx import template from a live import plan. Every column,
 * dropdown and instruction is derived from the introspected plan — nothing is
 * hardcoded. Three sheets: DATOS (fill here), INSTRUCCIONES (guide), _LISTAS
 * (hidden dropdown source).
 */
class TicketTemplateBuilder
{
    private const SHEET_DATA = 'DATOS';
    private const MAX_ROWS   = 5000;

    // ARGB palette (no emojis / em-dashes per project style).
    private const BLUE     = 'FF1773C8';
    private const BLUE_MED = 'FF4472C4';
    private const PURPLE   = 'FF7070A0';
    private const GREY     = 'FF7F7F7F';
    private const WHITE    = 'FFFFFFFF';
    private const GREEN_BG  = 'FFE2EFDA';
    private const GREEN_TXT = 'FF375623';
    private const HEAD_DK   = 'FF1F4E79';
    private const BORDER    = 'FFCCCCCC';

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
    ) {}

    /**
     * Generates the template for a container selection and returns the path.
     *
     * @param int[] $containerIds
     */
    public function build(array $containerIds, string $outputPath = ''): string
    {
        if ($outputPath === '') {
            $outputPath = rtrim(sys_get_temp_dir(), '/') . '/TEMPLATE_SERVICE_DESK.xlsx';
        }

        $plan    = $this->introspector->buildPlan($containerIds, true);
        $columns = $plan['columns'];

        $spreadsheet = new Spreadsheet();

        $listas = $spreadsheet->getActiveSheet();
        $listas->setTitle('_LISTAS');

        $datos = $spreadsheet->createSheet();
        $datos->setTitle(self::SHEET_DATA);

        $instr = $spreadsheet->createSheet();
        $instr->setTitle('INSTRUCCIONES');

        // Map each option-bearing column to a _LISTAS column letter.
        $listMap = $this->writeLists($listas, $columns);

        $this->writeData($datos, $columns, $listMap);
        $this->writeInstructions($instr, $columns);
        $this->writeMeta($spreadsheet, $containerIds);

        $spreadsheet->setActiveSheetIndex(1); // open on DATOS

        (new XlsxWriter($spreadsheet))->save($outputPath);

        return $outputPath;
    }

    /**
     * Writes the hidden _LISTAS sheet. Returns header => list column letter.
     *
     * @return array<string,string>
     */
    private function writeLists(Worksheet $ws, array $columns): array
    {
        $map    = [];
        $colIdx = 1;
        foreach ($columns as $col) {
            if (empty($col['options'])) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($colIdx);
            $ws->getCell($letter . '1')->setValue($col['header']);
            $ws->getCell($letter . '1')->getStyle()->getFont()->setBold(true);
            foreach ($col['options'] as $i => $value) {
                $ws->getCell($letter . ($i + 2))->setValue($value);
            }
            $map[$col['header']] = $letter;
            $colIdx++;
        }
        $ws->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        return $map;
    }

    /**
     * Writes the DATOS sheet: header row, an example row, and dropdown data
     * validations pointing at _LISTAS.
     *
     * @param array<string,string> $listMap
     */
    private function writeData(Worksheet $ws, array $columns, array $listMap): void
    {
        $fillFor = static function (array $col): string {
            if ($col['kind'] === 'output') {
                return self::GREY;
            }
            if (! empty($col['required'])) {
                return self::BLUE;
            }
            return $col['kind'] === 'plugin' ? self::BLUE_MED : self::PURPLE;
        };

        foreach ($columns as $idx => $col) {
            $letter = Coordinate::stringFromColumnIndex($idx + 1);

            // Header cell.
            $cell = $ws->getCell($letter . '1');
            $cell->setValue($col['header']);
            $cell->getStyle()->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillFor($col)]],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::WHITE]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER]]],
            ]);
            $ws->getColumnDimension($letter)->setWidth($this->widthFor($col));

            // Dropdown validation (data starts at row 2; there is no example row
            // in DATOS on purpose — examples live in INSTRUCCIONES).
            if (! empty($col['options']) && isset($listMap[$col['header']])) {
                $listCol = $listMap[$col['header']];
                $count   = count($col['options']);
                $formula = '_LISTAS!$' . $listCol . '$2:$' . $listCol . '$' . ($count + 1);
                $dv = $ws->getDataValidation($letter . '2:' . $letter . self::MAX_ROWS);
                $dv->setType(DataValidation::TYPE_LIST)
                    ->setAllowBlank(true)
                    // PhpSpreadsheet 3.x writes showDropDown = !getShowDropDown(),
                    // and OOXML showDropDown="1" HIDES the arrow. So true => "0"
                    // => the in-cell dropdown arrow is visible.
                    ->setShowDropDown(true)
                    ->setShowErrorMessage(true)
                    ->setErrorTitle('Valor no válido')
                    ->setError('Selecciona un valor de la lista.')
                    ->setFormula1($formula);
            }
        }

        $ws->freezePane('A2');
        $ws->getRowDimension(1)->setRowHeight(30);
    }

    /**
     * Hidden sheet that records which plugin containers this template targets,
     * so the upload flow can auto-detect them (no re-selection needed).
     *
     * @param int[] $containerIds
     */
    private function writeMeta(Spreadsheet $spreadsheet, array $containerIds): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('_META');
        $ws->getCell('A1')->setValue('containers');
        $ws->getCell('B1')->setValue(implode(',', $containerIds));
        $ws->getCell('A2')->setValue('generated_at');
        $ws->getCell('B2')->setValue(date('Y-m-d H:i:s'));
        $ws->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
    }

    /**
     * Reads the container ids recorded in a template's _META sheet.
     *
     * @return int[]
     */
    public static function readMetaContainers(Spreadsheet $spreadsheet): array
    {
        $ws = $spreadsheet->getSheetByName('_META');
        if ($ws === null) {
            return [];
        }
        $raw = (string) $ws->getCell('B1')->getValue();
        return array_values(array_filter(array_map(
            static fn($v) => (int) trim($v),
            explode(',', $raw),
        ), static fn($v) => $v > 0));
    }

    private function writeInstructions(Worksheet $ws, array $columns): void
    {
        $ws->mergeCells('A1:E1');
        $ws->getCell('A1')->setValue('TEMPLATE DE IMPORTACIÓN — SERVICE DESK');
        $ws->getStyle('A1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BLUE]],
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => self::WHITE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension(1)->setRowHeight(30);

        $headers = ['CAMPO', 'DESCRIPCIÓN', 'REQUERIDO', 'TIPO', 'VALORES / EJEMPLO'];
        $widths  = [32, 55, 14, 16, 55];
        foreach ($headers as $i => $h) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $ws->getCell($letter . '3')->setValue($h);
            $ws->getStyle($letter . '3')->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEAD_DK]],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::WHITE]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER]]],
            ]);
            $ws->getColumnDimension($letter)->setWidth($widths[$i]);
        }

        $row = 4;
        foreach ($columns as $col) {
            if ($col['kind'] === 'output') {
                $req = 'NO LLENAR';
            } else {
                $req = ! empty($col['required']) ? 'Sí' : 'No';
            }
            $tipo = $this->typeLabel($col['type']);
            $valores = ! empty($col['options'])
                ? $this->summarizeOptions($col['options'])
                : (string) ($col['example'] ?? '');

            $data = [$col['header'], (string) ($col['desc'] ?? ''), $req, $tipo, $valores];
            foreach ($data as $ci => $value) {
                $letter = Coordinate::stringFromColumnIndex($ci + 1);
                $cell = $ws->getCell($letter . $row);
                $cell->setValue($value);
                $cell->getStyle()->applyFromArray([
                    'font' => ['bold' => $ci === 0, 'size' => 9],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER]]],
                ]);
            }
            $row++;
        }
        $ws->freezePane('A4');
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'dropdown', 'enum', 'glpi_category', 'glpi_user' => 'Lista',
            'date'     => 'Fecha',
            'number'   => 'Número',
            'textarea' => 'Texto largo',
            default    => 'Texto',
        };
    }

    private function summarizeOptions(array $options): string
    {
        $count = count($options);
        if ($count <= 15) {
            return implode(', ', $options);
        }
        return implode(', ', array_slice($options, 0, 15)) . " ... (+{$count} opciones en la lista desplegable)";
    }

    private function widthFor(array $col): float
    {
        return match ($col['type']) {
            'textarea'      => 40,
            'date'          => 20,
            'glpi_category', 'glpi_user' => 32,
            default         => max(16, min(40, mb_strlen($col['header']) + 4)),
        };
    }
}
