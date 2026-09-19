<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\Reports\Models\ReportSnapshotModel;

/** API mirror of the Reports web controller. */
class ReportsApiController extends BaseApiController
{
    public function index()
    {
        return $this->success((new ReportSnapshotModel())->listRecent(24));
    }

    /** Renamed from show() to avoid ResourceController's show($id=null) signature. */
    public function period(int $year, int $month)
    {
        $row = (new ReportSnapshotModel())->findByPeriod($year, $month);
        if ($row === null) {
            return $this->notFound('Informe no encontrado para ese período.');
        }
        $row['payload'] = json_decode((string) $row['payload_json'], true) ?: new \stdClass();
        return $this->success($row);
    }

    public function generate(int $year, int $month)
    {
        try {
            $row = service('reportSnapshotBuilder')->generate($year, $month, (int) session()->get('user_id'), false);
            return $this->success($row, 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function regenerate(int $year, int $month)
    {
        try {
            $row = service('reportSnapshotBuilder')->generate($year, $month, (int) session()->get('user_id'), true);
            return $this->success($row);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function pptx(int $year, int $month)
    {
        $row = (new ReportSnapshotModel())->findByPeriod($year, $month);
        if ($row === null) {
            return $this->notFound('Informe no encontrado para ese período.');
        }
        $payload = json_decode((string) $row['payload_json'], true) ?: [];
        $period  = new \App\Modules\Reports\Services\ReportPeriod($year, $month);
        $path    = service('reportsPptxBuilder')->render((int) $row['id'], $payload, $period->label);
        return $this->success(['path' => $path]);
    }

    public function send(int $year, int $month)
    {
        $row = (new ReportSnapshotModel())->findByPeriod($year, $month);
        if ($row === null) {
            return $this->notFound('Informe no encontrado para ese período.');
        }

        $settings   = service('reportsSettings');
        $recipients = $settings->emailRecipients();
        if ($recipients === []) {
            return $this->error('No hay destinatarios configurados.');
        }

        $period  = new \App\Modules\Reports\Services\ReportPeriod($year, $month);
        $payload = json_decode((string) $row['payload_json'], true) ?: [];

        try {
            $pptx = $row['pptx_path'] && is_file($row['pptx_path'])
                ? $row['pptx_path']
                : service('reportsPptxBuilder')->render((int) $row['id'], $payload, $period->label);

            $mailer = new \App\Modules\Communications\Services\MailerService();
            $result = $mailer->sendReport(
                $recipients, [],
                config('Email')->fromEmail ?? 'noreply@ibrastudio.com',
                $settings->emailSenderName(),
                'Informe ejecutivo ' . $period->label,
                '<p>Se adjunta el informe ejecutivo de ' . esc($period->label) . '.</p>',
                [['path' => $pptx, 'name' => 'informe_' . $period->startDate . '.pptx']],
            );
            if (! $result['success']) {
                return $this->error($result['error']);
            }

            (new \App\Modules\Reports\Models\ReportDeliveryModel())->insert([
                'snapshot_id' => (int) $row['id'],
                'recipients'  => implode(',', $recipients),
                'status'      => 'sent',
                'sent_by'     => (int) session()->get('user_id'),
                'sent_at'     => date('Y-m-d H:i:s'),
            ]);

            return $this->success(['recipients' => $recipients]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
