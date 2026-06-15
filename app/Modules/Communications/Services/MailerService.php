<?php

namespace App\Modules\Communications\Services;

use CodeIgniter\Email\Email;

class MailerService
{
    private Email $mailer;

    public function __construct()
    {
        $this->mailer = $this->buildMailer();
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

    // -----------------------------------------------------------------------
    // SMTP configuration — reads from app_settings DB table (SuperAdmin),
    // falls back to Config/Email.php (.env values) if not configured.
    // -----------------------------------------------------------------------

    private function buildMailer(): Email
    {
        try {
            $svc = service('appSettings');

            if (! $svc->isSmtpConfigured()) {
                return \Config\Services::email(null, false);
            }

            $smtp = $svc->getSmtp();

            $config             = new \Config\Email();
            $config->protocol   = 'smtp';
            $config->SMTPHost   = $smtp['smtp_host'];
            $config->SMTPUser   = $smtp['smtp_user'];
            $config->SMTPPass   = $smtp['smtp_password'];
            $config->SMTPPort   = (int) ($smtp['smtp_port'] ?: 587);
            $config->SMTPCrypto = $smtp['smtp_crypto'] ?: 'tls';
            $config->fromEmail  = $smtp['smtp_from_email'];
            $config->fromName   = $smtp['smtp_from_name'];

            return \Config\Services::email($config, false);
        } catch (\Throwable $e) {
            log_message('error', '[MailerService] Could not load SMTP from DB, falling back to .env: ' . $e->getMessage());
            return \Config\Services::email(null, false);
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
