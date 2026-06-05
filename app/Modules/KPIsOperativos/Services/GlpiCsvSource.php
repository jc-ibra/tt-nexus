<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use DateTime;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lee tickets desde un export de GLPI en CSV o XLSX.
 *
 * - CSV: detecta encoding (utf-8/cp1252/iso-8859-1), respeta BOM,
 *   delimiter "," por defecto.
 * - XLSX: usa PhpSpreadsheet streaming-friendly.
 *
 * Normaliza filas al schema lógico de GlpiSchema::COLUMNS y parsea fechas
 * en multi-formato (espejo de parse_fechas_serie en el Python original).
 */
final class GlpiCsvSource implements GlpiTicketSourceInterface
{
    private string $path;
    private string $extension;
    private ?DateTimeInterface $from = null;
    private ?DateTimeInterface $to   = null;

    public function __construct(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Archivo no encontrado o no legible: {$path}");
        }

        $this->path      = $path;
        $this->extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($this->extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new RuntimeException("Extensión no soportada: .{$this->extension}");
        }
    }

    public function setDateRange(?DateTimeInterface $from, ?DateTimeInterface $to): void
    {
        $this->from = $from;
        $this->to   = $to;
    }

    public function describe(): string
    {
        return sprintf('%s (%s)', basename($this->path), strtoupper($this->extension));
    }

    public function tickets(): iterable
    {
        $rows = $this->extension === 'csv' ? $this->iterateCsv() : $this->iterateXlsx();

        foreach ($rows as $row) {
            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                continue;
            }

            // Filtro por rango de fechas (sobre fecha_apertura)
            if ($this->from || $this->to) {
                $fa = $normalized['fecha_apertura'];
                if (! $fa instanceof DateTimeInterface) {
                    continue;
                }
                if ($this->from && $fa < $this->from) {
                    continue;
                }
                if ($this->to && $fa > $this->to) {
                    continue;
                }
            }

            yield $normalized;
        }
    }

    // ─── CSV ──────────────────────────────────────────────────────────────

    /**
     * Lee el CSV con fgetcsv (file pointer) — respeta saltos de línea dentro
     * de campos entre comillas, lo cual ocurre en exports de GLPI con
     * descripciones largas.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function iterateCsv(): \Generator
    {
        // Detectar encoding leyendo solo el primer chunk
        $sample = file_get_contents($this->path, false, null, 0, 65536);
        if ($sample === false) {
            throw new RuntimeException('No se pudo leer el archivo CSV.');
        }

        // Strip BOM del sample para no engañar al detector
        $sampleNoBom = preg_replace('/^\xEF\xBB\xBF/', '', $sample) ?? $sample;
        $encoding    = mb_detect_encoding($sampleNoBom, GlpiSchema::CSV_ENCODINGS, true) ?: 'UTF-8';

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        // Si no es UTF-8, encadena un stream filter para convertir on-the-fly
        if ($encoding !== 'UTF-8') {
            stream_filter_append($handle, "convert.iconv.{$encoding}/UTF-8//IGNORE");
        }

        try {
            // Leer headers (descartar BOM si está en la primera celda)
            $headers = fgetcsv($handle, 0, ',', '"', '\\');
            if ($headers === false) {
                return;
            }

            $headers = array_map(static function ($h) {
                $h = (string) $h;
                // Strip BOM al inicio del primer header
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
                return trim($h);
            }, $headers);

            $expected = count($headers);

            while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // Saltar filas en blanco
                if (count($cells) === 1 && ($cells[0] === null || trim((string) $cells[0]) === '')) {
                    continue;
                }

                $row = [];
                foreach ($headers as $i => $h) {
                    $row[$h] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
                }

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    // ─── XLSX ─────────────────────────────────────────────────────────────

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function iterateXlsx(): \Generator
    {
        $reader = IOFactory::createReaderForFile($this->path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->path);
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = [];
        $first   = true;

        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $values = [];
            foreach ($cellIterator as $cell) {
                $values[] = (string) $cell->getFormattedValue();
            }

            if ($first) {
                $headers = array_map('trim', $values);
                $first   = false;
                continue;
            }

            // Skip filas completamente vacías
            $nonEmpty = array_filter($values, static fn($v) => $v !== '' && $v !== null);
            if (empty($nonEmpty)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = isset($values[$i]) ? trim((string) $values[$i]) : '';
            }

            yield $assoc;
        }
    }

    // ─── NORMALIZACIÓN ────────────────────────────────────────────────────

    /**
     * Mapea una fila cruda (header → valor) al schema lógico y parsea fechas.
     *
     * @param array<string, string> $raw
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $raw): ?array
    {
        $out = [];

        foreach (GlpiSchema::COLUMNS as $logical => $header) {
            $out[$logical] = $raw[$header] ?? '';
        }

        // Parse fechas
        $out['fecha_apertura'] = $this->parseDate($out['fecha_apertura']);
        $out['fecha_cierre']   = $this->parseDate($out['fecha_cierre']);

        // Filas sin estado se descartan (probable ruido o footers del export)
        if ($out['estado'] === '' && $out['titulo'] === '' && $out['glpi_id'] === '') {
            return null;
        }

        return $out;
    }

    private function parseDate(string $raw): ?DateTime
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Probar cada formato declarado en GlpiSchema.
        // No usamos comparación estricta de re-render: GLPI emite horas/días
        // sin cero a la izquierda ("9:12") que el strftime estricto rechazaría
        // aunque el parse haya sido correcto.
        foreach (GlpiSchema::DATE_FORMATS as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt === false) {
                continue;
            }

            $errors = DateTime::getLastErrors();
            $errCount  = is_array($errors) ? (int) ($errors['error_count']   ?? 0) : 0;
            $warnCount = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;

            if ($errCount === 0 && $warnCount === 0) {
                return $dt;
            }
        }

        // Fallback final: strtotime — ambiguo con d/m vs m/d, pero útil
        // para formatos ISO o muy desviados que no cubren los anteriores.
        $ts = strtotime($raw);
        if ($ts !== false) {
            return (new DateTime())->setTimestamp($ts);
        }

        return null;
    }
}
