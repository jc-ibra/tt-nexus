<?php

namespace App\Modules\Communications\Commands;

use App\Modules\Communications\Models\CommunicationLogModel;
use App\Modules\Communications\Models\CommunicationModel;
use App\Modules\Communications\Services\CommunicationService;
use App\Modules\Communications\Services\MailerService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ProcessQueue extends BaseCommand
{
    protected $group       = 'Communications';
    protected $name        = 'comms:process-queue';
    protected $description = 'Process the queued communications and send emails in batches.';
    protected $usage       = 'comms:process-queue [--batch <n>] [--throttle <s>] [--debug]';
    protected $options     = [
        '--batch'    => 'Number of emails per batch (default: COMMS_BATCH_SIZE env value).',
        '--throttle' => 'Seconds to sleep between batches (default: COMMS_THROTTLE_SECONDS env value).',
        '--debug'    => 'Show verbose output including SMTP debug info.',
    ];

    public function run(array $params): void
    {
        $batch    = (int) CLI::getOption('batch')    ?: (int) env('COMMS_BATCH_SIZE', 50);
        $throttle = (int) CLI::getOption('throttle') ?: (int) env('COMMS_THROTTLE_SECONDS', 2);
        $debug    = CLI::getOption('debug') !== null;

        $commModel = new CommunicationModel();
        $logModel  = new CommunicationLogModel();
        $mailer    = new MailerService();
        $svc       = service('communicationService');

        $queued = $commModel->whereIn('status', ['queued', 'sending'])->findAll();

        if (empty($queued)) {
            CLI::write('No hay comunicados en cola.', 'yellow');
            return;
        }

        CLI::write(sprintf('[%s] Procesando %d comunicado(s)…', date('H:i:s'), count($queued)), 'cyan');

        foreach ($queued as $comm) {
            $this->processCommunication($comm, $logModel, $mailer, $svc, $batch, $throttle, $debug);
        }

        CLI::write(sprintf('[%s] Cola procesada.', date('H:i:s')), 'green');
    }

    private function processCommunication(
        array $comm,
        CommunicationLogModel $logModel,
        MailerService $mailer,
        CommunicationService $svc,
        int $batch,
        int $throttle,
        bool $debug
    ): void {
        $commModel = new CommunicationModel();
        $commId    = (int) $comm['id'];

        CLI::write(sprintf('  Comunicado #%d: %s', $commId, $comm['subject']), 'white');

        // Mark as sending
        $commModel->skipValidation(true)->update($commId, ['status' => 'sending']);

        $htmlBody = $svc->buildEmailHtml($comm['subject'], $comm['body']);
        $offset   = 0;
        $sent     = 0;
        $failed   = 0;

        while (true) {
            $logs = $logModel
                ->where('communication_id', $commId)
                ->where('status', 'queued')
                ->limit($batch, $offset)
                ->findAll();

            if (empty($logs)) {
                break;
            }

            foreach ($logs as $log) {
                $result = $mailer->sendSingle(
                    $log['email'],
                    $log['name'],
                    $comm['from_email'],
                    $comm['from_name'],
                    $comm['subject'],
                    $htmlBody
                );

                $now = date('Y-m-d H:i:s');

                if ($result['success']) {
                    $logModel->update($log['id'], ['status' => 'sent', 'sent_at' => $now, 'error_message' => null]);
                    $sent++;
                    if ($debug) {
                        CLI::write(sprintf('    ✓ %s', $log['email']), 'green');
                    }
                } else {
                    $logModel->update($log['id'], ['status' => 'failed', 'error_message' => $result['error']]);
                    $failed++;
                    if ($debug) {
                        CLI::write(sprintf('    ✗ %s — %s', $log['email'], $result['error']), 'red');
                    }
                }
            }

            CLI::write(sprintf('    Lote: %d enviados, %d fallidos', $sent, $failed));

            if (count($logs) < $batch) {
                break;
            }

            if ($throttle > 0) {
                sleep($throttle);
            }

            $offset += $batch;
        }

        // Mark communication as sent or failed
        $stats      = $logModel->getStats($commId);
        $allDone    = ((int) $stats['queued']) === 0;
        $finalStatus = $failed > 0 && $sent === 0 ? 'failed' : ($allDone ? 'sent' : 'queued');

        $updateData = ['status' => $finalStatus];
        if ($finalStatus === 'sent') {
            $updateData['sent_at'] = date('Y-m-d H:i:s');
        }

        $commModel->skipValidation(true)->update($commId, $updateData);

        CLI::write(sprintf(
            '  #%d → %s  (%d enviados, %d fallidos)',
            $commId, strtoupper($finalStatus), $sent, $failed
        ), $finalStatus === 'sent' ? 'green' : ($finalStatus === 'failed' ? 'red' : 'yellow'));
    }
}
