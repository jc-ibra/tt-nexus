<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Models\GlpiTicketModel;
use App\Modules\KPIsOperativos\Services\GlpiIdcHomologator;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use DateTime;
use DateTimeInterface;
use Throwable;

/**
 * Orquesta el ciclo completo de un reporte GLPI:
 *   1. Mueve el archivo subido a writable/kpi/uploads/{report_id}/.
 *   2. Crea el registro en glpi_reports (status=processing).
 *   3. Invoca al GlpiTicketSourceInterface para iterar tickets.
 *   4. Inserta en glpi_tickets en batches; calcula horas_resolucion.
 *   5. Marca status=ready (o failed con error_message en caso de excepción).
 *
 * Fase 2: el snapshot kpi_json se deja vacío (lo llena GlpiKpiCalculator en fase 3).
 */
final class GlpiReportService
{
    private const INSERT_BATCH = 500;
    private const UPLOAD_DIR   = WRITEPATH . 'kpi/uploads';

    private BaseConnection $db;
    private GlpiReportModel $reports;
    private GlpiTicketModel $tickets;
    private GlpiIdcHomologator $homologator;

    public function __construct()
    {
        $this->db          = db_connect();
        $this->reports     = new GlpiReportModel();
        $this->tickets     = new GlpiTicketModel();
        $this->homologator = new GlpiIdcHomologator();
    }

    /**
     * Crea un reporte a partir de un archivo CSV/XLSX recién subido.
     *
     * @return array{success: bool, report_id?: int, error?: string}
     */
    public function createFromUpload(
        UploadedFile $file,
        string $name,
        ?string $periodStart,
        ?string $periodEnd,
        ?int $uploadedBy
    ): array {
        // Validación básica del archivo
        if (! $file->isValid() || $file->hasMoved()) {
            return ['success' => false, 'error' => 'El archivo no es válido o ya fue procesado.'];
        }

        $ext = strtolower($file->getExtension() ?: pathinfo((string) $file->getClientName(), PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            return ['success' => false, 'error' => 'Formato no soportado. Usa CSV o XLSX.'];
        }

        // 1. Crear el report en processing — necesitamos el id para nombrar la carpeta
        $reportId = $this->reports->insert([
            'name'            => trim($name),
            'period_start'    => $periodStart ?: null,
            'period_end'      => $periodEnd ?: null,
            'source_type'     => $ext === 'csv' ? 'csv' : 'xlsx',
            'source_filename' => $file->getClientName(),
            'status'          => 'processing',
            'total_tickets'   => 0,
            'uploaded_by'     => $uploadedBy,
        ], true);

        if (! $reportId) {
            return ['success' => false, 'error' => 'No se pudo crear el reporte en la base de datos.'];
        }

        // 2. Mover archivo
        try {
            $destDir = self::UPLOAD_DIR . '/' . $reportId;
            if (! is_dir($destDir) && ! mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
                throw new \RuntimeException("No se pudo crear el directorio: {$destDir}");
            }

            $storedName = 'source.' . $ext;
            $file->move($destDir, $storedName, true);
            $storedPath = $destDir . '/' . $storedName;

            $this->reports->update($reportId, ['source_path' => $storedPath]);
        } catch (Throwable $e) {
            $this->markFailed($reportId, 'Error al guardar archivo: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al guardar el archivo. Revisa permisos de writable/.'];
        }

        // 3-4. Parsear e insertar tickets
        try {
            $from = $periodStart ? new DateTime($periodStart . ' 00:00:00') : null;
            $to   = $periodEnd   ? new DateTime($periodEnd   . ' 23:59:59') : null;

            $source = new GlpiCsvSource($storedPath);
            $source->setDateRange($from, $to);

            $total = $this->ingestTickets($reportId, $source);

            $this->reports->update($reportId, [
                'total_tickets' => $total,
            ]);

            // Calcula y persiste el snapshot kpi_json
            (new GlpiKpiCalculator())->computeAndSave((int) $reportId);

            $this->reports->update($reportId, [
                'status' => 'ready',
            ]);

            return ['success' => true, 'report_id' => (int) $reportId];
        } catch (Throwable $e) {
            log_message('error', '[GlpiReportService] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->markFailed($reportId, $e->getMessage());
            // Limpiar tickets parciales para no dejar el reporte en estado inconsistente
            $this->tickets->deleteByReport((int) $reportId);
            return ['success' => false, 'error' => 'Error al procesar el archivo: ' . $e->getMessage()];
        }
    }

    /**
     * Itera la fuente e inserta en batches. Devuelve el total insertado.
     */
    private function ingestTickets(int $reportId, GlpiTicketSourceInterface $source): int
    {
        $batch = [];
        $total = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($source->tickets() as $t) {
            $rawIdc      = trim((string) ($t['idc'] ?? ''));
            $canonicalId = $rawIdc === '' ? null : $this->homologator->resolve($rawIdc, $reportId);

            $batch[] = $this->ticketToRow($reportId, $t, $now, $canonicalId);

            if (count($batch) >= self::INSERT_BATCH) {
                $this->tickets->insertBatchSafe($batch);
                $total += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->tickets->insertBatchSafe($batch);
            $total += count($batch);
        }

        return $total;
    }

    /**
     * Convierte el ticket normalizado a una fila lista para insertBatch.
     *
     * @param array<string, mixed> $t
     * @return array<string, mixed>
     */
    private function ticketToRow(int $reportId, array $t, string $now, ?int $idcCanonicalId = null): array
    {
        $fa = $t['fecha_apertura'] instanceof DateTimeInterface ? $t['fecha_apertura']->format('Y-m-d H:i:s') : null;
        $fc = $t['fecha_cierre']   instanceof DateTimeInterface ? $t['fecha_cierre']->format('Y-m-d H:i:s') : null;

        $horas = null;
        if ($fa && $fc) {
            $secs = strtotime($fc) - strtotime($fa);
            if ($secs >= 0) {
                $horas = round($secs / 3600, 2);
            }
        }

        return [
            'report_id'        => $reportId,
            'glpi_id'          => $this->trimToLength((string) ($t['glpi_id'] ?? ''), 40),
            'titulo'           => $this->trimToLength((string) ($t['titulo'] ?? ''), 500),
            'estado'           => $this->trimToLength((string) ($t['estado'] ?? ''), 60),
            'fecha_apertura'   => $fa,
            'fecha_cierre'     => $fc,
            'regional'         => $this->trimToLength((string) ($t['regional'] ?? ''), 120),
            'estado_geo'       => $this->trimToLength((string) ($t['estado_geo'] ?? ''), 120),
            'municipio'        => $this->trimToLength((string) ($t['municipio'] ?? ''), 120),
            'proyecto'         => $this->trimToLength((string) ($t['proyecto'] ?? ''), 160),
            'categoria'        => $this->trimToLength((string) ($t['categoria'] ?? ''), 160),
            'solicitud'        => $this->trimToLength((string) ($t['solicitud'] ?? ''), 120),
            'idc'              => $this->trimToLength((string) ($t['idc'] ?? ''), 160),
            'idc_canonical_id' => $idcCanonicalId,
            'urgencia'         => $this->trimToLength((string) ($t['urgencia'] ?? ''), 40),
            'impacto'          => $this->trimToLength((string) ($t['impacto'] ?? ''), 40),
            'sucursal'         => $this->trimToLength((string) ($t['sucursal'] ?? ''), 255),
            'cliente'          => $this->trimToLength((string) ($t['cliente'] ?? ''), 160),
            'horas_resolucion' => $horas,
            'created_at'       => $now,
        ];
    }

    private function trimToLength(string $value, int $maxLen): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLen) {
            return mb_substr($value, 0, $maxLen);
        }

        return $value;
    }

    public function markFailed(int $reportId, string $reason): void
    {
        $this->reports->update($reportId, [
            'status'        => 'failed',
            'error_message' => mb_substr($reason, 0, 1000),
        ]);
    }

    /**
     * Elimina un reporte y todos sus tickets (cascade), más el archivo en disco.
     */
    public function deleteReport(int $reportId): void
    {
        $report = $this->reports->find($reportId);
        if (! $report) {
            return;
        }

        if (! empty($report['source_path']) && is_file($report['source_path'])) {
            @unlink($report['source_path']);
        }

        $dir = self::UPLOAD_DIR . '/' . $reportId;
        if (is_dir($dir)) {
            $this->removeDir($dir);
        }

        // FK ON DELETE CASCADE limpia los tickets
        $this->reports->delete($reportId);
    }

    private function removeDir(string $dir): void
    {
        $files = scandir($dir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir . '/' . $f;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
