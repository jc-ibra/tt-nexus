<?php

namespace App\Modules\Communications\Services;

use CodeIgniter\Email\Email;

class MailerService
{
    private Email $mailer;

    public function __construct()
    {
        $this->mailer = \Config\Services::email(null, false);
    }

    /**
     * Send a single email for a communication.
     *
     * @return array{success: bool, error: string}
     */
    public function sendSingle(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $htmlBody,
        int $priority = 3,
        bool $requestReadReceipt = false
    ): array {
        try {
            $this->mailer->clear();
            $this->mailer->setFrom($fromEmail, $fromName);
            $this->mailer->setTo($toEmail, $toName);
            $this->mailer->setSubject($subject);
            $this->mailer->setMessage($htmlBody);
            $this->mailer->setMailType('html');

            if ($priority === 1) {
                $this->mailer->setPriority(1);
                $this->mailer->setHeader('X-Priority', '1');
                $this->mailer->setHeader('X-MSMail-Priority', 'High');
                $this->mailer->setHeader('Importance', 'High');
            }

            if ($requestReadReceipt) {
                $this->mailer->setHeader('Disposition-Notification-To', $fromEmail);
            }

            if ($this->mailer->send(false)) {
                return ['success' => true, 'error' => ''];
            }

            return ['success' => false, 'error' => $this->extractError()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractError(): string
    {
        $debug = $this->mailer->printDebugger(['headers']);

        // Strip HTML tags and trim, keep it concise
        $plain = strip_tags((string) $debug);

        return mb_substr(trim($plain), 0, 500);
    }
}
