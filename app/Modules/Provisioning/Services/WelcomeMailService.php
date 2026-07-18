<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Provisioning\Models\ProvisioningSystemModel;

/**
 * Sends the provisioning welcome email to a new employee's primary mailbox.
 *
 * Reuses the Communications `mailer` service (which reads the SMTP already
 * configured in Nexus). The email restates the temporary password and lists
 * every system the employee can reach with that single credential.
 *
 * Everything the operator can tune lives in provisioning_settings and is
 * edited from /admin/provisioning/settings:
 *   - the on/off toggle (welcome_email_enabled),
 *   - the subject and the key copy blocks (welcome_email_*).
 * The system login URLs are read from each system's base_url (the same URL
 * shown at /admin/provisioning/systems), never hardcoded.
 */
class WelcomeMailService
{
    /** Public path (relative to FCPATH) to the logo shown in the header. */
    private const LOGO_PATH = 'img/tt-logo.png';

    /**
     * Default, admin-editable settings. Single source of truth shared by the
     * settings form and this service; mirrored by the seeding migration.
     *
     * @return array<string,string>
     */
    public static function defaults(): array
    {
        return [
            'welcome_email_enabled'         => '1',
            'welcome_email_subject'         => 'Bienvenido: tus accesos y contrasena temporal',
            'welcome_email_org_name'        => 'Trantor Technologies',
            'welcome_email_help_desk_email' => 'mesadeayuda@trantortechnologies.mx',
            'welcome_email_hero_eyebrow'    => 'Te damos la bienvenida',
            'welcome_email_hero_title'      => 'Bienvenido a {org}',
            'welcome_email_hero_intro'      => 'Nos da mucho gusto tenerte en el equipo. Ya dejamos listos tus accesos para que empieces con el pie derecho.',
            'welcome_email_security_notice' => 'esta contrasena es temporal. Por tu seguridad, cambiala lo antes posible en cada sistema.',
            'welcome_email_support_intro'   => 'Si tienes algun problema con tus accesos, reportalo directamente a la mesa de ayuda central:',
            'welcome_email_footer'          => 'Este mensaje se genero automaticamente al crear tus cuentas. No es necesario responderlo.',
        ];
    }

    /**
     * @param array  $employee           employee row (name, lastname, ...)
     * @param string $loginEmail         primary institutional email = the login
     * @param string $tempPassword       temporary password created for the alta
     * @param array  $provisionedSystems system rows that were created successfully
     *
     * @return array{success: bool, error: string, skipped?: bool}
     */
    public function sendWelcome(array $employee, string $loginEmail, string $tempPassword, array $provisionedSystems): array
    {
        // Global kill-switch controlled by the SuperAdmin. When off, we skip
        // silently (success = true) so provisioning is never marked as failed.
        if (! $this->isEnabled()) {
            log_message('info', '[WelcomeMailService] Welcome email disabled by settings; skipped for ' . $loginEmail);
            return ['success' => true, 'error' => '', 'skipped' => true];
        }

        $loginEmail = trim($loginEmail);
        if ($loginEmail === '' || ! str_contains($loginEmail, '@')) {
            return ['success' => false, 'error' => 'Correo principal invalido; no se envio la bienvenida.'];
        }

        $employeeName = trim(($employee['name'] ?? '') . ' ' . ($employee['lastname'] ?? ''));
        if ($employeeName === '') {
            $employeeName = $loginEmail;
        }

        $composed = $this->compose($employeeName, $loginEmail, $tempPassword, $provisionedSystems);

        return $this->deliver($loginEmail, $employeeName, $composed['subject'], $composed['html']);
    }

    /**
     * Sends a sample of the welcome email to an arbitrary address so an admin
     * can preview the current copy. Ignores the on/off toggle (a test is an
     * explicit action) and uses the live active systems for realistic links.
     *
     * @return array{success: bool, error: string}
     */
    public function sendTest(string $toEmail): array
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Correo de prueba invalido.'];
        }

        $sampleName     = 'Nombre Apellido';
        $samplePassword = 'Temporal-1234';
        $systems        = (new ProvisioningSystemModel())->listActive();

        $composed = $this->compose($sampleName, $toEmail, $samplePassword, $systems);

        return $this->deliver($toEmail, $sampleName, '[PRUEBA] ' . $composed['subject'], $composed['html']);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Builds the subject + HTML body from the configurable settings.
     *
     * @return array{subject: string, html: string}
     */
    private function compose(string $employeeName, string $loginEmail, string $tempPassword, array $provisionedSystems): array
    {
        $orgName = $this->conf('welcome_email_org_name');

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
            'orgName'        => $orgName,
            'logoUrl'        => is_file(FCPATH . self::LOGO_PATH) ? base_url(self::LOGO_PATH) : null,
            'employeeName'   => $employeeName,
            'loginEmail'     => $loginEmail,
            'tempPassword'   => $tempPassword,
            'systems'        => $systems,
            'helpDeskEmail'  => $this->conf('welcome_email_help_desk_email'),
            'heroEyebrow'    => $this->orgToken($this->conf('welcome_email_hero_eyebrow'), $orgName),
            'heroTitle'      => $this->orgToken($this->conf('welcome_email_hero_title'), $orgName),
            'heroIntro'      => $this->orgToken($this->conf('welcome_email_hero_intro'), $orgName),
            'securityNotice' => $this->orgToken($this->conf('welcome_email_security_notice'), $orgName),
            'supportIntro'   => $this->orgToken($this->conf('welcome_email_support_intro'), $orgName),
            'footerText'     => $this->orgToken($this->conf('welcome_email_footer'), $orgName),
        ]);

        return [
            'subject' => $this->orgToken($this->conf('welcome_email_subject'), $orgName),
            'html'    => $html,
        ];
    }

    /**
     * @return array{success: bool, error: string}
     */
    private function deliver(string $toEmail, string $toName, string $subject, string $html): array
    {
        $from = $this->resolveFrom();

        return service('mailerService')->sendSingle(
            $toEmail,
            $toName,
            $from['email'],
            $from['name'],
            $subject,
            $html,
            3,
            false
        );
    }

    private function isEnabled(): bool
    {
        return $this->conf('welcome_email_enabled') === '1';
    }

    /**
     * Reads a welcome_* setting, falling back to the shared default when the
     * stored value is empty (so a cleared field never blanks the email).
     */
    private function conf(string $key): string
    {
        try {
            $val = trim((string) service('provisioningSettings')->get($key, ''));
        } catch (\Throwable $e) {
            $val = '';
        }

        return $val !== '' ? $val : (self::defaults()[$key] ?? '');
    }

    /** Replaces the {org} token with the organization name. */
    private function orgToken(string $text, string $orgName): string
    {
        return str_replace('{org}', $orgName, $text);
    }

    /**
     * Resolves the human login URL for a system from its configuration: an
     * explicit portal_url/login_url override in options first, otherwise the
     * base_url shown at /admin/provisioning/systems (cleaned of the GLPI
     * apirest.php suffix). No URL is hardcoded.
     */
    private function resolveLoginUrl(array $system): string
    {
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
