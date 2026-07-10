<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Connectors;

use App\Modules\Mailboxes\Libraries\MailcowApi;
use App\Modules\Mailboxes\Models\MailboxesSettingsModel;

/**
 * Mailcow connector.
 *
 * Wraps the existing Mailboxes module's HTTP client (App\Modules\Mailboxes\Libraries\MailcowApi).
 * The Mailboxes module is NOT modified.
 *
 * Two operating modes (decided by provisioning_systems.auth_type):
 *   - "mailcow_settings_reuse" → reuse URL + API key already configured in Mailboxes (recommended).
 *   - "mailcow_own_credentials" → use Provisioning's own credentials (api_key) and base_url.
 */
class MailcowConnector implements SystemConnector
{
    private MailcowApi $api;
    private string $defaultDomain;
    private int    $defaultQuotaMb;

    public function __construct(array $system, array $credentials, array $options = [])
    {
        $authType  = (string) ($system['auth_type'] ?? 'mailcow_settings_reuse');

        if ($authType === 'mailcow_settings_reuse') {
            $settings = new MailboxesSettingsModel();
            $baseUrl  = $settings->get('mailcow_url');
            $apiKey   = $settings->get('mailcow_api_key');
            $verify   = (bool) $settings->get('mailcow_verify_ssl', '1');
        } else {
            $baseUrl  = (string) ($system['base_url'] ?? '');
            $apiKey   = (string) ($credentials['api_key'] ?? '');
            $verify   = (bool) ($options['verify_ssl'] ?? true);
        }

        $this->api            = new MailcowApi($baseUrl, $apiKey, $verify);
        $this->defaultDomain  = (string) ($options['default_domain'] ?? '');
        $this->defaultQuotaMb = (int) ($options['default_quota_mb'] ?? 1024);
    }

    public function key(): string
    {
        return 'mailcow';
    }

    public function verifyConnection(): ConnectorResult
    {
        $resp = $this->api->getVersion();
        if (! $resp['success']) {
            return ConnectorResult::fail((string) ($resp['error'] ?? 'Error desconocido'), 'MAILCOW_PING_FAILED');
        }
        $version = $resp['data']['version'] ?? 'desconocida';
        return ConnectorResult::ok("Conexión con Mailcow verificada. Versión: {$version}");
    }

    public function createUser(array $userData): ConnectorResult
    {
        $email = trim((string) ($userData['email'] ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return ConnectorResult::fail('Correo de trabajo inválido para crear buzón.', 'MAILCOW_INVALID_EMAIL');
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($this->defaultDomain !== '' && $domain !== $this->defaultDomain) {
            // Honor the email domain but log the mismatch for visibility.
            log_message('info', "[MailcowConnector] Domain {$domain} differs from configured default {$this->defaultDomain}");
        }

        $password = (string) ($userData['password'] ?? '');
        if (strlen($password) < 8) {
            return ConnectorResult::fail('La contraseña debe tener al menos 8 caracteres.', 'MAILCOW_WEAK_PASSWORD');
        }

        $fullName = trim(($userData['name'] ?? '') . ' ' . ($userData['lastname'] ?? ''));

        $resp = $this->api->addMailbox([
            'local_part' => $local,
            'domain'     => $domain,
            'name'       => $fullName !== '' ? $fullName : $local,
            'password'   => $password,
            'password2'  => $password,
            'quota'      => $this->defaultQuotaMb,
            'active'     => 1,
        ]);

        return $this->interpret($resp, "Buzón {$email} creado en Mailcow.", $email, 'MAILCOW_CREATE_FAILED');
    }

    public function disableUser(string $externalId, array $userData = []): ConnectorResult
    {
        $resp = $this->api->editMailbox([$externalId], ['active' => 0]);
        return $this->interpret($resp, "Buzón {$externalId} desactivado en Mailcow.", $externalId, 'MAILCOW_DISABLE_FAILED');
    }

    public function enableUser(string $externalId, string $newPassword, array $userData = []): ConnectorResult
    {
        if (strlen($newPassword) < 8) {
            return ConnectorResult::fail('La contraseña debe tener al menos 8 caracteres.', 'MAILCOW_WEAK_PASSWORD');
        }
        // Re-enable the mailbox and rotate the password in the same edit.
        $resp = $this->api->editMailbox([$externalId], [
            'active'    => 1,
            'password'  => $newPassword,
            'password2' => $newPassword,
        ]);
        return $this->interpret($resp, "Buzón {$externalId} reactivado en Mailcow.", $externalId, 'MAILCOW_ENABLE_FAILED');
    }

    public function changePassword(string $externalId, string $newPassword, array $userData = []): ConnectorResult
    {
        if (strlen($newPassword) < 8) {
            return ConnectorResult::fail('La contraseña debe tener al menos 8 caracteres.', 'MAILCOW_WEAK_PASSWORD');
        }
        $resp = $this->api->editMailbox([$externalId], [
            'password'  => $newPassword,
            'password2' => $newPassword,
        ]);
        return $this->interpret($resp, "Contraseña actualizada en Mailcow para {$externalId}.", $externalId, 'MAILCOW_PASSWORD_FAILED');
    }

    public function updateUser(string $externalId, array $userData): ConnectorResult
    {
        $attrs = [];
        $fullName = trim(($userData['name'] ?? '') . ' ' . ($userData['lastname'] ?? ''));
        if ($fullName !== '') {
            $attrs['name'] = $fullName;
        }
        if (empty($attrs)) {
            return ConnectorResult::ok("Sin cambios aplicables en Mailcow para {$externalId}.", $externalId);
        }
        $resp = $this->api->editMailbox([$externalId], $attrs);
        return $this->interpret($resp, "Buzón {$externalId} actualizado en Mailcow.", $externalId, 'MAILCOW_UPDATE_FAILED');
    }

    /**
     * Interprets a MailcowApi write response.
     *
     * Mailcow answers HTTP 200 for add/edit/delete even on logical failures
     * (mailbox not found, password rejected by policy, etc.), encoding the real
     * outcome in the JSON body as objects with a `type` (success|danger|error|
     * warning|info) and `msg`. MailcowApi only checks the HTTP status, so those
     * failures would otherwise be logged as success. This treats any `danger`/
     * `error` entry in the body as a failure.
     */
    private function interpret(array $resp, string $okMsg, string $externalId, string $errCode): ConnectorResult
    {
        if (! $resp['success']) {
            return ConnectorResult::fail((string) ($resp['error'] ?? 'Error desconocido'), $errCode, $resp['data'] ?? null);
        }

        $bodyError = $this->mailcowBodyError($resp['data'] ?? null);
        if ($bodyError !== null) {
            log_message('error', "[MailcowConnector] {$errCode}: {$bodyError}");
            return ConnectorResult::fail($bodyError, $errCode, $resp['data'] ?? null);
        }

        return ConnectorResult::ok($okMsg, $externalId, $resp['data'] ?? null);
    }

    /**
     * Returns a human-readable error when the Mailcow response body reports a
     * `danger`/`error` outcome, or null when no failure is present.
     */
    private function mailcowBodyError(mixed $data): ?string
    {
        // Normalize to a list of result objects (Mailcow may return a single one).
        $items = (is_array($data) && isset($data['type'])) ? [$data] : $data;
        if (! is_array($items)) {
            return null; // unparseable body: don't override the HTTP-level result
        }

        $messages = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['type'])) {
                continue;
            }
            if (in_array(strtolower((string) $item['type']), ['danger', 'error'], true)) {
                $messages[] = $this->flattenMailcowMsg($item['msg'] ?? $item['type']);
            }
        }

        return $messages ? implode(' | ', $messages) : null;
    }

    private function flattenMailcowMsg(mixed $msg): string
    {
        if (is_array($msg)) {
            return trim(implode(' ', array_map(
                static fn($m) => is_scalar($m) ? (string) $m : json_encode($m),
                $msg
            )));
        }
        return is_scalar($msg) ? (string) $msg : (string) json_encode($msg);
    }
}
