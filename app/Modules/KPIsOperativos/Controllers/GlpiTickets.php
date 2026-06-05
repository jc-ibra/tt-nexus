<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Controllers;

use App\Controllers\BaseController;
use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use App\Modules\KPIsOperativos\Models\GlpiTicketModel;
use App\Modules\KPIsOperativos\Config\GlpiSchema;
use App\Modules\KPIsOperativos\Services\GlpiAreaKpiCalculator;
use App\Modules\KPIsOperativos\Services\GlpiAreaPptxRenderer;
use App\Modules\KPIsOperativos\Services\GlpiPptxRenderer;
use App\Modules\KPIsOperativos\Services\GlpiReportService;

class GlpiTickets extends BaseController
{
    public function index(): string
    {
        $reports = (new GlpiReportModel())
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->findAll();

        return view('App\Modules\KPIsOperativos\Views\glpi\index', [
            'pageTitle' => 'GLPI Tickets',
            'reports'   => $reports,
        ]);
    }

    public function upload(): string
    {
        return view('App\Modules\KPIsOperativos\Views\glpi\upload', [
            'pageTitle' => 'Subir reporte GLPI',
        ]);
    }

    public function store()
    {
        $name        = trim((string) $this->request->getPost('name'));
        $periodStart = $this->request->getPost('period_start') ?: null;
        $periodEnd   = $this->request->getPost('period_end') ?: null;
        $file        = $this->request->getFile('file');

        if ($name === '') {
            session()->setFlashdata('errors', ['El nombre del reporte es obligatorio.']);
            return redirect()->back()->withInput();
        }

        if (! $file) {
            session()->setFlashdata('errors', ['Adjunta un archivo CSV o XLSX.']);
            return redirect()->back()->withInput();
        }

        // Validación de tipo y tamaño (25 MB) — coincide con el hint del form
        $validation = service('validation');
        $validation->setRules([
            'file' => [
                'label' => 'Archivo',
                'rules' => 'uploaded[file]|max_size[file,25600]|ext_in[file,csv,xlsx,xls]',
                'errors' => [
                    'uploaded'  => 'Debes adjuntar un archivo.',
                    'max_size'  => 'El archivo supera los 25 MB.',
                    'ext_in'    => 'El archivo debe ser CSV, XLSX o XLS.',
                ],
            ],
        ]);

        if (! $validation->withRequest($this->request)->run()) {
            session()->setFlashdata('errors', $validation->getErrors());
            return redirect()->back()->withInput();
        }

        $userId = (int) (session()->get('user_id') ?: 0) ?: null;

        $svc    = new GlpiReportService();
        $result = $svc->createFromUpload($file, $name, $periodStart, $periodEnd, $userId);

        if (! $result['success']) {
            session()->setFlashdata('errors', [$result['error'] ?? 'Error desconocido al procesar el reporte.']);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', 'Reporte procesado correctamente.');
        return redirect()->to(route_to('kpi.glpi.show', $result['report_id']));
    }

    public function show(int $id): string
    {
        $report = (new GlpiReportModel())->find($id);
        if (! $report) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $kpi = null;
        if (! empty($report['kpi_json'])) {
            $decoded = json_decode($report['kpi_json'], true);
            if (is_array($decoded) && ! empty($decoded['total'])) {
                $kpi = $decoded;
            }
        }

        // Si el reporte está listo y el snapshot está disponible → dashboard completo.
        // En cualquier otro caso (processing/failed o sin snapshot) → vista de metadata + sample.
        if ($report['status'] === 'ready' && $kpi !== null) {
            return view('App\Modules\KPIsOperativos\Views\glpi\dashboard', [
                'pageTitle' => $report['name'],
                'report'    => $report,
                'kpi'       => $kpi,
            ]);
        }

        $sampleTickets = (new GlpiTicketModel())
            ->where('report_id', $id)
            ->orderBy('fecha_apertura', 'DESC')
            ->limit(10)
            ->findAll();

        return view('App\Modules\KPIsOperativos\Views\glpi\show', [
            'pageTitle'     => $report['name'],
            'report'        => $report,
            'sampleTickets' => $sampleTickets,
        ]);
    }

    public function destroy(int $id)
    {
        $report = (new GlpiReportModel())->find($id);
        if (! $report) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        (new GlpiReportService())->deleteReport($id);

        session()->setFlashdata('success', "Reporte «{$report['name']}» eliminado.");
        return redirect()->to(route_to('kpi.glpi.index'));
    }

    public function pptx(int $id)
    {
        $report = (new GlpiReportModel())->find($id);
        if (! $report) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ($report['status'] !== 'ready' || empty($report['kpi_json'])) {
            session()->setFlashdata('errors', ['El reporte aún no está listo o no tiene snapshot KPI.']);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        $kpi = json_decode($report['kpi_json'], true);
        if (! is_array($kpi)) {
            session()->setFlashdata('errors', ['Snapshot KPI inválido. Recalcula con kpi:recompute.']);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        // Genera (o regenera) el .pptx
        try {
            $path = (new GlpiPptxRenderer())->render((int) $id, $kpi, (string) $report['name']);
        } catch (\Throwable $e) {
            log_message('error', '[GlpiPptxRenderer] ' . $e->getMessage());
            session()->setFlashdata('errors', ['Error al generar PPTX: ' . $e->getMessage()]);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        if (! is_file($path)) {
            session()->setFlashdata('errors', ['El archivo no se pudo escribir en disco.']);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $report['name']) ?? 'reporte';
        return $this->response->download($path, null)
            ->setFileName("kpi_glpi_{$safeName}.pptx");
    }

    public function pptxAreas(int $id)
    {
        $report = (new GlpiReportModel())->find($id);
        if (! $report) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ($report['status'] !== 'ready' || empty($report['kpi_json'])) {
            session()->setFlashdata('errors', ['El reporte aún no está listo o no tiene snapshot KPI.']);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        $kpiGlobal = json_decode($report['kpi_json'], true);
        if (! is_array($kpiGlobal)) {
            session()->setFlashdata('errors', ['Snapshot KPI inválido. Recalcula con kpi:recompute.']);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        try {
            $kpiByArea = (new GlpiAreaKpiCalculator())->computeAll((int) $id);
            $path = (new GlpiAreaPptxRenderer())
                ->renderByArea((int) $id, $kpiByArea, $kpiGlobal, (string) $report['name']);
        } catch (\Throwable $e) {
            log_message('error', '[GlpiAreaPptxRenderer] ' . $e->getMessage());
            session()->setFlashdata('errors', ['Error al generar PPTX por área: ' . $e->getMessage()]);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        if (! is_file($path)) {
            session()->setFlashdata('errors', ['El archivo no se pudo escribir en disco.']);
            return redirect()->to(route_to('kpi.glpi.show', $id));
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $report['name']) ?? 'reporte';
        return $this->response->download($path, null)
            ->setFileName("kpi_glpi_{$safeName}_por_area.pptx");
    }

    public function tickets(int $id): string
    {
        $report = (new GlpiReportModel())->find($id);
        if (! $report) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $filterKeys = ['estado', 'regional', 'estado_geo', 'idc', 'categoria', 'proyecto'];
        $filters    = [];

        foreach ($filterKeys as $key) {
            $val = $this->request->getGet($key);
            if ($val !== null && $val !== '') {
                $filters[$key] = (string) $val;
            }
        }

        // Pseudo-filtro especial: ?envios=1 → categoria LIKE %ENVI%
        $envios = (string) ($this->request->getGet('envios') ?? '');
        if ($envios === '1') {
            $filters['envios'] = '1';
        }

        // Pseudo-filtro: ?sin_idc=1 → tickets sin IDC asignado
        // (idc IS NULL o "SIN ASIGNAR"), excluyendo Control de Envíos.
        $sinIdc = (string) ($this->request->getGet('sin_idc') ?? '');
        if ($sinIdc === '1') {
            $filters['sin_idc'] = '1';
        }

        $perPage = max(10, min(200, (int) ($this->request->getGet('per_page') ?? 50)));
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));

        $builder = (new GlpiTicketModel())->where('report_id', $id);
        foreach ($filters as $key => $val) {
            if ($key === 'envios') {
                $builder->like('categoria', 'ENVI', 'both', null, true);
                continue;
            }
            if ($key === 'sin_idc') {
                $builder->groupStart()
                    ->where('idc', null)
                    ->orWhere('idc', GlpiSchema::IDC_UNASSIGNED)
                ->groupEnd()
                ->where(GlpiSchema::notEnviosSqlCondition(), null, false);
                continue;
            }
            $builder->where($key, $val);
        }

        $total   = $builder->countAllResults(false);
        $tickets = $builder->orderBy('fecha_apertura', 'DESC')
                           ->findAll($perPage, ($page - 1) * $perPage);

        return view('App\Modules\KPIsOperativos\Views\glpi\tickets_index', [
            'pageTitle'  => 'Tickets — ' . $report['name'],
            'report'     => $report,
            'tickets'    => $tickets,
            'filters'    => $filters,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'lastPage'   => max(1, (int) ceil($total / $perPage)),
        ]);
    }
}
