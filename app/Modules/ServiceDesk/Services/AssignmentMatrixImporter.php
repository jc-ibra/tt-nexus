<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\ServiceDesk\Models\ServiceDeskAssignmentModel;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Models\ServiceDeskSettingsModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses the assignment matrix workbook and replaces the stored matrix.
 *
 * Expected shape (this is asignaciones.xlsx):
 *
 *      A                     | B  C  D  C ... (4 columns per agent)   | ..  legend
 *   1  Proyecto Categoria    | Raul        | Cinthya      | ...       | A   Apertura
 *   2                        | AV A  D  C  | AV A  D  C   | ...       | D   Documentación
 *   3  AD > Almacén > ...    |    E  E  E  |              |           | E   Email
 *
 * Row 1 names the agent (merged across their four columns), row 2 names the
 * stage, column A carries the GLPI category completename, and each cell holds
 * the channel code. To the right of the matrix, an optional two-column block
 * spells out what every code means; it is stored so the on-screen legend keeps
 * up with the sheet instead of being hardcoded.
 *
 * The matrix ends at the first column whose row-2 value is not a stage code,
 * which is what keeps the legend block out of it.
 */
class AssignmentMatrixImporter
{
    /** Stage codes, in reading order, with the labels used when the sheet has no legend. */
    public const STAGES = [
        'AV' => 'Apertura por viáticos',
        'A'  => 'Apertura',
        'D'  => 'Documentación',
        'C'  => 'Cierre',
    ];

    /** Channel codes the sheet is known to use, with their fallback labels. */
    public const CHANNELS = [
        'E'   => 'Email',
        'W'   => 'WhatsApp / Telegram',
        'I'   => 'Importación semanal',
        'N/A' => 'No aplica',
    ];

    /** servicedesk_settings keys this importer writes. */
    public const KEY_LEGEND   = 'assignments_legend';
    public const KEY_UPDATED  = 'assignments_updated_at';
    public const KEY_FILENAME = 'assignments_source_file';
    public const KEY_UPDATER  = 'assignments_updated_by';

    public function __construct(
        private ServiceDeskAssignmentModel $assignments,
        private ServiceDeskCategoryMapModel $categoryMap,
        private ServiceDeskSettingsModel $settings,
    ) {}

    /**
     * Parses a workbook. Pure: it touches no database, so a malformed file is
     * reported in full before anything replaces a good matrix.
     *
     * @return ServiceResult data: array{agents:list<string>, cells:list<array>,
     *                                   legend:array<string,string>, categories:int}
     */
    public function parse(string $path): ServiceResult
    {
        if (! is_file($path)) {
            return ServiceResult::fail('No se encontró el archivo a procesar.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            log_message('error', '[Assignments] No se pudo leer el archivo: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo leer el archivo. Debe ser un .xlsx válido.');
        }

        // false, false => raw values, no formatting, indexed from 0.
        $grid    = $sheet->toArray(null, true, false, false);
        $rowOne  = $grid[0] ?? [];
        $rowTwo  = $grid[1] ?? [];
        $lastCol = count($rowTwo);

        // ------------------------------------------------------------------
        // Columns: walk right from B while row 2 still names a stage.
        // ------------------------------------------------------------------
        $columns   = [];   // column index => ['agent' => string, 'stage' => string]
        $agents    = [];   // agent names, in sheet order
        $lastAgent = '';
        for ($c = 1; $c < $lastCol; $c++) {
            $stage = strtoupper($this->clean((string) ($rowTwo[$c] ?? '')));
            if (! isset(self::STAGES[$stage])) {
                break; // end of the matrix; anything further right is the legend
            }
            $header = $this->clean((string) ($rowOne[$c] ?? ''));
            if ($header !== '') {
                $lastAgent = $header;      // start of a new agent group
                $agents[]  = $header;
            }
            if ($lastAgent === '') {
                continue; // stage column with no agent above it: nothing to attribute
            }
            $columns[$c] = ['agent' => $lastAgent, 'stage' => $stage];
        }

        if ($columns === [] || $agents === []) {
            return ServiceResult::fail(
                'El archivo no tiene la estructura esperada: la fila 1 debe nombrar a cada persona '
                . 'y la fila 2 la etapa (AV, A, D, C) de cada columna.'
            );
        }

        // ------------------------------------------------------------------
        // Rows: from row 3 down, column A is the GLPI category.
        // ------------------------------------------------------------------
        $cells = [];
        $seen  = [];
        $order = 0;

        foreach ($grid as $i => $row) {
            if ($i < 2) {
                continue;
            }
            $category = $this->clean((string) ($row[0] ?? ''));
            if ($category === '') {
                continue;
            }
            $key = mb_strtolower($category);
            if (isset($seen[$key])) {
                return ServiceResult::fail(
                    'La categoría «' . $category . '» aparece más de una vez en el archivo. '
                    . 'Debe haber una sola fila por categoría.'
                );
            }
            $seen[$key] = true;

            foreach ($columns as $c => $meta) {
                $channel = $this->clean((string) ($row[$c] ?? ''));
                if ($channel === '') {
                    continue;
                }
                $cells[] = [
                    'category_name'    => $category,
                    'glpi_category_id' => null, // resolved against Nexus in import()
                    'row_order'        => $order,
                    'agent'            => $meta['agent'],
                    'stage'            => $meta['stage'],
                    'channel'          => mb_substr($this->normalizeChannel($channel), 0, 40),
                ];
            }
            $order++;
        }

        if ($cells === []) {
            return ServiceResult::fail('El archivo no tiene ninguna asignación: todas las celdas están vacías.');
        }

        return ServiceResult::ok([
            'agents'     => $agents,
            'cells'      => $cells,
            'legend'     => $this->readLegend($grid, array_key_last($columns) + 1),
            'categories' => $order,
        ]);
    }

    /**
     * Parses and, if the file is sound, replaces the stored matrix. The old
     * matrix stays untouched when parsing fails.
     */
    public function import(string $path, string $originalName = '', ?int $userId = null): ServiceResult
    {
        $parsed = $this->parse($path);
        if (! $parsed->success) {
            return $parsed;
        }

        $data = $parsed->data;

        // Attach the GLPI id to the categories Nexus already knows about. This
        // is informational only, so a category the SuperAdmin has never mapped
        // still shows up in the matrix, just without an id.
        $idByName = $this->categoryIdsByName();
        foreach ($data['cells'] as $i => $cell) {
            $data['cells'][$i]['glpi_category_id'] = $idByName[$this->categoryKey($cell['category_name'])] ?? null;
        }

        $this->assignments->replaceAll($data['agents'], $data['cells']);

        $this->settings->setMany([
            self::KEY_LEGEND   => json_encode($data['legend'], JSON_UNESCAPED_UNICODE),
            self::KEY_UPDATED  => date('Y-m-d H:i:s'),
            self::KEY_FILENAME => mb_substr($originalName, 0, 190),
            self::KEY_UPDATER  => (string) ($userId ?? ''),
        ]);

        return ServiceResult::ok($data, sprintf(
            'Matriz actualizada: %d categorías, %d personas y %d asignaciones.',
            $data['categories'],
            count($data['agents']),
            count($data['cells'])
        ));
    }

    /**
     * Code => label for every stage and channel, taking the sheet's own legend
     * when it carried one and falling back to the built-in labels otherwise.
     *
     * @return array<string,string>
     */
    public function legend(): array
    {
        $stored = json_decode($this->settings->get(self::KEY_LEGEND, ''), true);
        $stored = is_array($stored) ? $stored : [];

        return array_merge(self::STAGES, self::CHANNELS, array_filter(
            $stored,
            static fn($v, $k): bool => is_string($k) && is_string($v) && $v !== '',
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Reads the "code | meaning" block sitting to the right of the matrix.
     * Returns an empty array when the sheet has none.
     *
     * @param list<array<int, mixed>> $grid
     * @return array<string,string>
     */
    private function readLegend(array $grid, int $fromCol): array
    {
        $best = [];
        $maxCols = 0;
        foreach ($grid as $row) {
            $maxCols = max($maxCols, count($row));
        }

        for ($c = $fromCol; $c < $maxCols - 1; $c++) {
            $pairs = [];
            foreach ($grid as $row) {
                $code  = strtoupper($this->clean((string) ($row[$c] ?? '')));
                $label = $this->clean((string) ($row[$c + 1] ?? ''));
                if ($code === '' || $label === '' || mb_strlen($code) > 8) {
                    continue;
                }
                $pairs[$code] = $label;
            }
            if (count($pairs) > count($best)) {
                $best = $pairs;
            }
        }

        return $best;
    }

    /**
     * Channel codes as written, with the spacing homologated so "E/W", "E / W"
     * and "e /w" all end up as a single value the view can split on "/".
     */
    private function normalizeChannel(string $raw): string
    {
        $parts = array_filter(array_map(
            fn(string $p): string => strtoupper($this->clean($p)),
            explode('/', str_replace('N/A', 'N|A', $raw))
        ), static fn(string $p): bool => $p !== '');

        return str_replace('N|A', 'N/A', implode(' / ', $parts));
    }

    /**
     * Best-effort category name => GLPI id, from the categories the SuperAdmin
     * has already mapped. Unknown categories simply carry a null id.
     *
     * @return array<string,int>
     */
    private function categoryIdsByName(): array
    {
        $out = [];
        foreach ($this->categoryMap->all() as $id => $row) {
            $name = $this->categoryKey((string) ($row['category_name'] ?? ''));
            if ($name !== '') {
                $out[$name] = (int) $id;
            }
        }
        return $out;
    }

    /** Comparison key for a category path: case- and spacing-insensitive. */
    private function categoryKey(string $name): string
    {
        $name = preg_replace('/\s*>\s*/u', '>', $this->clean($name)) ?? $name;
        return mb_strtolower($name);
    }

    /** Trims and collapses whitespace, including the non-breaking kind Excel emits. */
    private function clean(string $value): string
    {
        $value = str_replace(["\xc2\xa0", "\n", "\r", "\t"], ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
