<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

/**
 * Sends the provisioning welcome email to a new employee's primary mailbox.
 *
 * Reuses the Communications `mailer` service (which reads the SMTP already
 * configured in Nexus). The email restates the temporary password and lists
 * every system the employee can reach with that single credential.
 */
class WelcomeMailService
{
    /** Organization name shown in the email header/hero. */
    private const ORG_NAME = 'Trantor Technologies';

    /** Central help desk shown in the email footer. */
    private const HELP_DESK_EMAIL = 'mesadeayuda@trantortechnologies.mx';

    /** Public path (relative to FCPATH) to the logo shown in the header. */
    private const LOGO_PATH = 'img/tt-logo.png';

    /** Login URLs shown in the email, by system key (hardcoded per request). */
    private const LOGIN_URLS = [
        'intranet' => 'https://intranet.trantortechnologies.mx',
        'glpi'     => 'https://helpdesk.trantortechnologies.mx',
    ];

    /**
     * @param array  $employee           employee row (name, lastname, ...)
     * @param string $loginEmail         primary institutional email = the login
     * @param string $tempPassword       temporary password created for the alta
     * @param array  $provisionedSystems system rows that were created successfully
     *
     * @return array{success: bool, error: string}
     */
    public function sendWelcome(array $employee, string $loginEmail, string $tempPassword, array $provisionedSystems): array
    {
        $loginEmail = trim($loginEmail);
        if ($loginEmail === '' || ! str_contains($loginEmail, '@')) {
            return ['success' => false, 'error' => 'Correo principal invalido; no se envio la bienvenida.'];
        }

        $employeeName = trim(($employee['name'] ?? '') . ' ' . ($employee['lastname'] ?? ''));
        if ($employeeName === '') {
            $employeeName = $loginEmail;
        }

        $from    = $this->resolveFrom();
        $systems = [];
        foreach ($provisionedSystems as $s) {
            // Skip the mailbox itself: the email already landed there, and its
            // exact address (staff vs. main domain) is not worth surfacing here.
            if (($s['key'] ?? '') === 'mailcow') {
                continue;
            }
            $systems[] = [
                'name' => (string) ($s['name'] ?? $s['key'] ?? ''),
                'url'  => $this->resolveLoginUrl($s),
            ];
        }

        $html = view('App\Modules\Provisioning\Views\emails\welcome', [
            'orgName'       => self::ORG_NAME,
            'logoUrl'       => is_file(FCPATH . self::LOGO_PATH) ? base_url(self::LOGO_PATH) : null,
            'employeeName'  => $employeeName,
            'loginEmail'    => $loginEmail,
            'tempPassword'  => $tempPassword,
            'systems'       => $systems,
            'helpDeskEmail' => self::HELP_DESK_EMAIL,
        ]);

        return service('mailerService')->sendSingle(
            $loginEmail,
            $employeeName,
            $from['email'],
            $from['name'],
            'Bienvenido: tus accesos y contrasena temporal',
            $html,
            3,
            false
        );
    }

    /**
     * Resolves the human login URL for a system. Uses the hardcoded map first;
     * otherwise an explicit `portal_url`/`login_url` in the system options, then
     * the API base_url (cleaned up for GLPI).
     */
    private function resolveLoginUrl(array $system): string
    {
        $key = (string) ($system['key'] ?? '');
        if (isset(self::LOGIN_URLS[$key])) {
            return self::LOGIN_URLS[$key];
        }

        $options = [];
        if (! empty($system['options'])) {
            $decoded = json_decode((string) $system['options'], true);
            $options = is_array($decoded) ? $decoded : [];
        }

        foreach (['portal_url', 'login_url'] as $optKey) {
            if (! empty($options[$optKey])) {
                return (string) $options[$optKey];
            }
        }

        $url = trim((string) ($system['base_url'] ?? ''));
        // GLPI's base_url is usually the apirest.php endpoint; the login portal
        // is its parent path.
        return (string) preg_replace('#/apirest\.php/?$#i', '/', $url);
    }

    /**
     * @return array{email: string, name: string}
     */
    private function resolveFrom(): array
    {
        try {
            $svc = service('appSettings');
            if ($svc->isSmtpConfigured()) {
                $smtp = $svc->getSmtp();
                $email = trim((string) ($smtp['smtp_from_email'] ?? ''));
                if ($email !== '') {
                    return ['email' => $email, 'name' => trim((string) ($smtp['smtp_from_name'] ?? ''))];
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[WelcomeMailService] Could not read SMTP from settings: ' . $e->getMessage());
        }

        $cfg = config('Email');
        return [
            'email' => (string) ($cfg->fromEmail ?? 'no-reply@localhost'),
            'name'  => (string) ($cfg->fromName ?? 'Nexus'),
        ];
    }
}
