<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Models\GlpiTicketModel;
use App\Modules\KPIsOperativos\Services\GlpiKpiCalculator;
use App\Modules\KPIsOperativos\Services\GlpiPptxRenderer;
use App\Modules\KPIsOperativos\Services\GlpiReportService;

class GlpiReportsApiController extends BaseApiController
{
    public function index()
    {
        $page    = max(1, (int) ($this->request->getGet('page')  ?? 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?? 20)));

        $model = new GlpiReportModel();
        $total = $model->countAllResults(false);
        $items = $model->orderBy('created_at', 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        $items = array_map(static function ($r) {
            unset($r['kpi_json']); // demasiado grande para listado; usar /show
            return $r;
        }, $items);

        return $this->successPaginated($items, $this->buildMeta($total, $perPage, $page));
    }

    public function show($id = null)
    {
        $report = (new GlpiReportModel())->find((int) $id);
        if (! $report) {
            return $this->notFound('Reporte no encontrado.');
        }

        if (! empty($report['kpi_json'])) {
            $decoded = json_decode($report['kpi_json'], true);
            $report['kpi'] = is_array($decoded) ? $decoded : null;
            unset($report['kpi_json']);
        }

        return $this->success($report);
    }

    public function create()
    {
        $file = $this->request->getFile('file');
        if (! $file) {
            return $this->validationError(['file' => 'Adjunta un archivo CSV o XLSX en el campo "file".']);
        }

        $name = (string) $this->request->getPost('name');
        if ($name === '') {
            return $this->validationError(['name' => 'El nombre del reporte es obligatorio.']);
        }

        $userId = (int) (session()->get('user_id') ?: 0) ?: null;

        $result = (new GlpiReportService())->createFromUpload(
            $file,
            $name,
            $this->request->getPost('period_start') ?: null,
            $this->request->getPost('period_end')   ?: null,
            $userId
        );

        if (! $result['success']) {
            return $this->error($result['error'] ?? 'Error al procesar el reporte.');
        }

        $report = (new GlpiReportModel())->find((int) $result['report_id']);
        if ($report && ! empty($report['kpi_json'])) {
            $report['kpi'] = json_decode($report['kpi_json'], true);
            unset($report['kpi_json']);
        }

        return $this->successCreated($report);
    }

    public function delete($id = null)
    {
        $report = (new GlpiReportModel())->find((int) $id);
        if (! $report) {
            return $this->notFound('Reporte no encontrado.');
        }

        (new GlpiReportService())->deleteReport((int) $id);
        return $this->success(['deleted_id' => (int) $id]);
    }

    /**
     * Drill-down: lista paginada de tickets de un reporte.
     * Soporta filtros: estado, regional, idc, categoria.
     */
    public function tickets($id = null)
    {
        $report = (new GlpiReportModel())->find((int) $id);
        if (! $report) {
            return $this->notFound('Reporte no encontrado.');
        }

        $page    = max(1, (int) ($this->request->getGet('page')  ?? 1));
        $perPage = max(1, min(200, (int) ($this->request->getGet('per_page') ?? 50)));

        $model = (new GlpiTicketModel())->where('report_id', (int) $id);

        foreach (['estado', 'regional', 'idc', 'categoria', 'estado_geo'] as $col) {
            $val = $this->request->getGet($col);
            if ($val !== null && $val !== '') {
                $model->where($col, $val);
            }
        }

        $total = $model->countAllResults(false);
        $items = $model->orderBy('fecha_apertura', 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        return $this->successPaginated($items, $this->buildMeta($total, $perPage, $page));
    }

    /**
     * Recalcula el snapshot KPI desde los tickets ingeridos.
     */
    public function recompute($id = null)
    {
        $report = (new GlpiReportModel())->find((int) $id);
        if (! $report) {
            return $this->notFound('Reporte no encontrado.');
        }

        try {
            $kpi = (new GlpiKpiCalculator())->computeAndSave((int) $id);
        } catch (\Throwable $e) {
            return $this->error('Error al recalcular: ' . $e->getMessage(), 500);
        }

        return $this->success(['report_id' => (int) $id, 'kpi' => $kpi]);
    }

    /**
     * Genera (o regenera) el .pptx del reporte y devuelve metadata.
     */
    public function pptx($id = null)
    {
        $report = (new GlpiReportModel())->find((int) $id);
        if (! $report) {
            return $this->notFound('Reporte no encontrado.');
        }

        if ($report['status'] !== 'ready' || empty($report['kpi_json'])) {
            return $this->error('El reporte no está listo o no tiene snapshot KPI.', 422);
        }

        $kpi = json_decode($report['kpi_json'], true);
        if (! is_array($kpi)) {
            return $this->error('Snapshot KPI inválido.', 422);
        }

        try {
            $path = (new GlpiPptxRenderer())->render((int) $id, $kpi, (string) $report['name']);
        } catch (\Throwable $e) {
            return $this->error('Error al generar PPTX: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'report_id'    => (int) $id,
            'pptx_path'    => $path,
            'pptx_url'     => site_url('kpi/glpi/' . (int) $id . '/pptx'),
            'size_bytes'   => is_file($path) ? filesize($path) : 0,
        ]);
    }
}
