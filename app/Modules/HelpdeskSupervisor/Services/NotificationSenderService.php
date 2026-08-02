<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\NotificationModel;

/**
 * Orchestrates the Fase 2 notification lifecycle: prepare (IA draft + Excel ->
 * notification row) and send (via Communications' MailerService with the Excel
 * attached).
 */
class NotificationSenderService
{
    public function __construct(
        private NotificationDraftService $draft,
        private NotificationExcelService $excel,
        private HelpdeskSupervisorSettings $settings,
        private NotificationModel $model,
        private AuditRunModel $runs,
    ) {}

    /**
     * Builds (or rebuilds) the draft + Excel for an agent and upserts the
     * notification row. Returns the notification id in data.
     */
    public function prepare(int $auditRunId, int $glpiUserId, string $supervisorName = ''): ServiceResult
    {
        $run = $this->runs->find($auditRunId);
        if ($run === null) {
            return ServiceResult::fail('Auditoría no encontrada.');
        }

        $draftRes = $this->draft->generateDraft($auditRunId, $glpiUserId, $supervisorName);

        // No deviations at all -> nothing to notify (spec: show notice, no record).
        if (! $draftRes['ok'] && ! isset($draftRes['total_deviations'])) {
            return ServiceResult::fail($draftRes['error'] ?? 'El agente no tiene desviaciones.');
        }

        $excelPath = $this->excel->generateExcel($auditRunId, $glpiUserId);
        $agentName = (string) ($draftRes['agent_name'] ?? '');
        $nexusId   = isset($draftRes['nexus_user_id']) && $draftRes['nexus_user_id'] !== null
            ? (int) $draftRes['nexus_user_id'] : null;
        $bodyHtml  = $draftRes['ok'] ? (string) $draftRes['draft_html'] : '';

        $row = [
            'audit_run_id'     => $auditRunId,
            'glpi_user_id'     => $glpiUserId,
            'nexus_user_id'    => $nexusId,
            'agent_name'       => $agentName,
            'agent_email'      => $this->resolveAgentEmail($nexusId),
            'period_start'     => $run['period_start'],
            'period_end'       => $run['period_end'],
            'total_deviations' => (int) ($draftRes['total_deviations'] ?? 0),
            'ai_draft_body'    => $bodyHtml,
            'final_body'       => $bodyHtml,
            'excel_path'       => $excelPath !== '' ? $excelPath : null,
            'status'           => $draftRes['ok'] ? 'ready' : 'draft',
            'ai_tokens_input'  => (int) ($draftRes['input_tokens'] ?? 0),
            'ai_tokens_output' => (int) ($draftRes['output_tokens'] ?? 0),
            'error_message'    => $draftRes['ok'] ? null : ($draftRes['error'] ?? null),
        ];

        $existing = $this->model->forAgentRun($auditRunId, $glpiUserId);
        if ($existing) {
            $this->model->update((int) $existing['id'], $row);
            $id = (int) $existing['id'];
        } else {
            $id = (int) $this->model->insert($row, true);
        }

        $msg = $draftRes['ok']
            ? 'Borrador generado.'
            : ('No se pudo generar el borrador con IA (' . ($draftRes['error'] ?? '') . '). Puedes redactarlo manualmente.');

        return ServiceResult::ok(['notification_id' => $id, 'ai_ok' => $draftRes['ok']], $msg);
    }

    /**
     * Sends a prepared notification. $overrides may carry to, cc (array),
     * subject and final_body edited by the supervisor.
     */
    public function send(int $notificationId, array $overrides, ?int $sentByUserId): ServiceResult
    {
        $n = $this->model->find($notificationId);
        if ($n === null) {
            return ServiceResult::fail('Notificación no encontrada.');
        }

        $to = trim((string) ($overrides['to'] ?? $n['agent_email']));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('Falta un correo de destinatario válido para el agente.');
        }

        $cc = $overrides['cc'] ?? $this->settings->notificationCc();
        if (! is_array($cc)) {
            $cc = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string) $cc) ?: []));
        }

        $subject = trim((string) ($overrides['subject'] ?? ''));
        if ($subject === '') {
            $subject = 'Reporte de desviaciones - ' . $this->d($n['period_start']) . ' a ' . $this->d($n['period_end']) . ' - ' . $n['agent_name'];
        }

        $body = (string) ($overrides['final_body'] ?? $n['final_body'] ?? '');
        if (trim(strip_tags($body)) === '') {
            return ServiceResult::fail('El cuerpo del correo está vacío.');
        }

        [$fromEmail, $fromName] = $this->resolveSender();
        if ($fromEmail === '') {
            return ServiceResult::fail('No hay un remitente configurado (revisa el SMTP o el remitente de notificaciones).');
        }

        $attachments = [];
        if (! empty($n['excel_path']) && is_file($n['excel_path'])) {
            $attachments[] = ['path' => $n['excel_path'], 'name' => basename($n['excel_path'])];
        }

        $result = service('mailer')->sendReport([$to], array_values($cc), $fromEmail, $fromName, $subject, $body, $attachments);

        if (! $result['success']) {
            $this->model->update($notificationId, ['status' => 'failed', 'final_body' => $body, 'error_message' => mb_substr($result['error'], 0, 2000)]);
            return ServiceResult::fail('No se pudo enviar el correo: ' . $result['error']);
        }

        $this->model->update($notificationId, [
            'status'          => 'sent',
            'final_body'      => $body,
            'sent_at'         => date('Y-m-d H:i:s'),
            'sent_by_user_id' => $sentByUserId,
            'error_message'   => null,
        ]);

        return ServiceResult::ok(['notification_id' => $notificationId], 'Correo enviado a ' . $to . '.');
    }

    private function resolveAgentEmail(?int $nexusUserId): string
    {
        if (! $nexusUserId) {
            return '';
        }
        $row = \Config\Database::connect()->table('core_users')->select('email')->where('id', $nexusUserId)->get()->getRow();
        return $row ? (string) $row->email : '';
    }

    /** @return array{0:string,1:string} [fromEmail, fromName] */
    private function resolveSender(): array
    {
        $email = $this->settings->notificationSenderEmail();
        $name  = $this->settings->notificationSenderName() ?: 'Gerencia de Service Desk';

        if ($email === '') {
            try {
                $smtp  = service('appSettings')->getSmtp();
                $email = (string) ($smtp['smtp_from_email'] ?? '') ?: (string) config('Email')->fromEmail;
                if ($name === 'Gerencia de Service Desk' && ! empty($smtp['smtp_from_name'])) {
                    $name = (string) $smtp['smtp_from_name'];
                }
            } catch (\Throwable) {
                $email = (string) config('Email')->fromEmail;
            }
        }

        return [$email, $name];
    }

    private function d(mixed $date): string
    {
        $ts = strtotime((string) $date);
        return $ts ? date('d/m/Y', $ts) : (string) $date;
    }
}
