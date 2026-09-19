<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Controllers\BaseController;
use App\Modules\Reports\Models\ReportCommentaryModel;
use App\Modules\Reports\Models\ReportDeliveryModel;
use App\Modules\Reports\Models\ReportSnapshotModel;
use App\Modules\Reports\Services\ReportPeriod;
use CodeIgniter\Exceptions\PageNotFoundException;

class Reports extends BaseController
{
    public function index(): string
    {
        $snapshots = (new ReportSnapshotModel())->listRecent(24);

        return view('App\Modules\Reports\Views\index', [
            'pageTitle' => 'Informes',
            'snapshots' => $snapshots,
        ]);
    }

    public function show(int $year, int $month)
    {
        $snapshot = $this->findOrFail($year, $month);
        $payload  = $this->decodePayload($snapshot);
        $commentary = (new ReportCommentaryModel())->forSnapshot((int) $snapshot['id']);

        return view('App\Modules\Reports\Views\show', [
            'pageTitle'  => 'Informe ' . (new ReportPeriod($year, $month))->label,
            'period'     => new ReportPeriod($year, $month),
            'snapshot'   => $snapshot,
            'payload'    => $payload,
            'commentary' => $commentary,
        ]);
    }

    public function present(int $year, int $month)
    {
        $snapshot   = $this->findOrFail($year, $month);
        $payload    = $this->decodePayload($snapshot);
        $commentary = (new ReportCommentaryModel())->forSnapshot((int) $snapshot['id']);

        return view('App\Modules\Reports\Views\present', [
            'pageTitle'  => 'Presentación ' . (new ReportPeriod($year, $month))->label,
            'period'     => new ReportPeriod($year, $month),
            'snapshot'   => $snapshot,
            'payload'    => $payload,
            'commentary' => $commentary,
        ]);
    }

    /**
     * Live drill-down: this deliberately does NOT read the frozen payload —
     * it queries GLPI right now, so the banner "detalle al día de hoy" is
     * always true. The month stays frozen; only this screen is live.
     */
    public function drill(int $year, int $month)
    {
        $snapshot = $this->findOrFail($year, $month);
        $period   = new ReportPeriod($year, $month);
        $provider = service('reportsGlpiTicketsProvider');

        $live = $provider->isAvailable() ? $provider->collect($period) : ['available' => false];

        return view('App\Modules\Reports\Views\drill', [
            'pageTitle' => 'Detalle en vivo ' . $period->label,
            'period'    => $period,
            'snapshot'  => $snapshot,
            'live'      => $live,
        ]);
    }

    public function pptx(int $year, int $month)
    {
        $snapshot = $this->findOrFail($year, $month);
        $period   = new ReportPeriod($year, $month);
        $payload  = $this->decodePayload($snapshot);

        $path = $snapshot['pptx_path'] && is_file($snapshot['pptx_path'])
            ? $snapshot['pptx_path']
            : service('reportsPptxBuilder')->render((int) $snapshot['id'], $payload, $period->label);

        return $this->response->download($path, null)
            ->setFileName('informe_' . $year . '_' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.pptx');
    }

    public function xlsx(int $year, int $month)
    {
        $snapshot = $this->findOrFail($year, $month);
        $period   = new ReportPeriod($year, $month);
        $payload  = $this->decodePayload($snapshot);

        $path = $snapshot['xlsx_path'] && is_file($snapshot['xlsx_path'])
            ? $snapshot['xlsx_path']
            : service('reportsXlsxBuilder')->render((int) $snapshot['id'], $payload, $period->label);

        return $this->response->download($path, null)
            ->setFileName('informe_' . $year . '_' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.xlsx');
    }

    public function generate(int $year, int $month)
    {
        try {
            service('reportSnapshotBuilder')->generate($year, $month, (int) session()->get('user_id'), false);
            session()->setFlashdata('success', 'Informe generado.');
        } catch (\Throwable $e) {
            session()->setFlashdata('error', $e->getMessage());
        }
        return redirect()->to(route_to('reports.show', $year, $month));
    }

    public function commentary(int $year, int $month)
    {
        $snapshot = $this->findOrFail($year, $month);
        $section  = (string) $this->request->getPost('section_key');
        $body     = (string) $this->request->getPost('body');

        if ($section === '') {
            session()->setFlashdata('error', 'Sección inválida.');
            return redirect()->to(route_to('reports.show', $year, $month));
        }

        if ($section === 'summary_ai') {
            $section = 'summary';
            $result  = service('reportsNarrative')->draftSummary((int) $snapshot['id'], $this->decodePayload($snapshot), (int) session()->get('user_id'));
            session()->setFlashdata($result->success ? 'success' : 'error', $result->message);
            return redirect()->to(route_to('reports.show', $year, $month));
        }

        (new ReportCommentaryModel())->upsert((int) $snapshot['id'], $section, $body, false, (int) session()->get('user_id'));
        session()->setFlashdata('success', 'Comentario guardado.');
        return redirect()->to(route_to('reports.show', $year, $month));
    }

    public function send(int $year, int $month)
    {
        $snapshot   = $this->findOrFail($year, $month);
        $settings   = service('reportsSettings');
        $recipients = $settings->emailRecipients();

        if ($recipients === []) {
            session()->setFlashdata('error', 'No hay destinatarios configurados. Revisa /admin/reports/settings.');
            return redirect()->to(route_to('reports.show', $year, $month));
        }

        $period  = new ReportPeriod($year, $month);
        $payload = $this->decodePayload($snapshot);
        $model   = new ReportDeliveryModel();

        try {
            $pptx = $snapshot['pptx_path'] && is_file($snapshot['pptx_path'])
                ? $snapshot['pptx_path']
                : service('reportsPptxBuilder')->render((int) $snapshot['id'], $payload, $period->label);

            $mailer = new \App\Modules\Communications\Services\MailerService();
            $result = $mailer->sendReport(
                $recipients,
                [],
                config('Email')->fromEmail ?? 'noreply@ibrastudio.com',
                $settings->emailSenderName(),
                'Informe ejecutivo ' . $period->label,
                '<p>Se adjunta el informe ejecutivo de ' . esc($period->label) . '.</p>',
                [['path' => $pptx, 'name' => 'informe_' . $period->startDate . '.pptx']],
            );
            if (! $result['success']) {
                throw new \RuntimeException($result['error']);
            }

            $model->insert([
                'snapshot_id' => (int) $snapshot['id'],
                'recipients'  => implode(',', $recipients),
                'status'      => 'sent',
                'sent_by'     => (int) session()->get('user_id'),
                'sent_at'     => date('Y-m-d H:i:s'),
            ]);
            session()->setFlashdata('success', 'Informe enviado a ' . count($recipients) . ' destinatario(s).');
        } catch (\Throwable $e) {
            $model->insert([
                'snapshot_id' => (int) $snapshot['id'],
                'recipients'  => implode(',', $recipients),
                'status'      => 'failed',
                'error'       => substr($e->getMessage(), 0, 1000),
                'sent_by'     => (int) session()->get('user_id'),
                'sent_at'     => date('Y-m-d H:i:s'),
            ]);
            session()->setFlashdata('error', 'No se pudo enviar: ' . $e->getMessage());
        }

        return redirect()->to(route_to('reports.show', $year, $month));
    }

    private function findOrFail(int $year, int $month): array
    {
        $snapshot = (new ReportSnapshotModel())->findByPeriod($year, $month);
        if ($snapshot === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $snapshot;
    }

    private function decodePayload(array $snapshot): array
    {
        $decoded = json_decode((string) ($snapshot['payload_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }
}
