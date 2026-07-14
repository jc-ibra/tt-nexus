<?php

declare(strict_types=1);

namespace App\Modules\Mailboxes\Services;

use App\Modules\Mailboxes\Libraries\MailcowApi;
use App\Modules\Mailboxes\Models\MailboxesSettingsModel;
use App\Modules\Core\Services\ServiceResult;

class MailboxesService
{
    private MailboxesSettingsModel $settings;
    private ?MailcowApi $api = null;

    public function __construct(MailboxesSettingsModel $settings)
    {
        $this->settings = $settings;
    }

    private function api(): MailcowApi
    {
        if ($this->api === null) {
            $this->api = new MailcowApi(
                $this->settings->get('mailcow_url'),
                $this->settings->get('mailcow_api_key'),
                (bool) $this->settings->get('mailcow_verify_ssl', '1'),
            );
        }
        return $this->api;
    }

    // -----------------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------------

    public function getSettings(): array
    {
        return $this->settings->getAll();
    }

    public function saveSettings(array $data): ServiceResult
    {
        $allowed = ['mailcow_url', 'mailcow_api_key', 'mailcow_verify_ssl', 'default_quota_mb'];
        $clean   = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $clean[$key] = trim((string) $data[$key]);
            }
        }

        // Checkboxes send nothing when unchecked
        $clean['mailcow_verify_ssl'] = isset($data['mailcow_verify_ssl']) ? '1' : '0';

        $this->settings->setMany($clean);
        $this->api = null; // reset cached instance

        log_message('info', '[Mailboxes] Settings updated by user_id=' . session()->get('user_id'));

        return ServiceResult::ok(null, 'Configuración guardada correctamente.');
    }

    // -----------------------------------------------------------------------
    // Connection test
    // -----------------------------------------------------------------------

    public function testConnection(): ServiceResult
    {
        $result = $this->api()->getVersion();

        if (! $result['success']) {
            return ServiceResult::fail($result['error'] ?? 'Error desconocido.');
        }

        $version = $result['data']['version'] ?? 'desconocida';
        return ServiceResult::ok(['version' => $version], "Conexión exitosa. Versión Mailcow: {$version}");
    }

    // -----------------------------------------------------------------------
    // Mailboxes
    // -----------------------------------------------------------------------

    public function listMailboxes(): ServiceResult
    {
        $result = $this->api()->getAllMailboxes();

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        $mailboxes = is_array($result['data']) ? $result['data'] : [];
        return ServiceResult::ok($this->normalizeMailboxes($mailboxes));
    }

    public function getMailbox(string $email): ServiceResult
    {
        $result = $this->api()->getMailbox($email);

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        $data = is_array($result['data']) ? $result['data'] : [];

        // API returns array when listing all, object when getting one
        if (isset($data[0])) {
            $data = $data[0];
        }

        return ServiceResult::ok($this->normalizeMailbox($data));
    }

    /**
     * Lightweight existence check for a specific mailbox. Mailcow's
     * get/mailbox/{email} returns HTTP 200 with an empty array when the mailbox
     * does not exist, so "not found" is NOT an API error — we detect it by the
     * absence of a matching username. Returns fail only on a real connection /
     * configuration error, so callers can distinguish "cannot verify" from
     * "verified: does not exist".
     */
    public function mailboxExists(string $email): ServiceResult
    {
        $email  = trim($email);
        $result = $this->api()->getMailbox($email);

        if (! $result['success']) {
            return ServiceResult::fail($result['error'] ?? 'No se pudo consultar Mailcow.');
        }

        $data = is_array($result['data']) ? $result['data'] : [];
        if (isset($data[0])) {
            $data = $data[0];
        }

        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $exists   = $username !== '' && $username === strtolower($email);

        return ServiceResult::ok(['exists' => $exists]);
    }

    public function getDomains(): ServiceResult
    {
        $result = $this->api()->getAllDomains();

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        $domains = is_array($result['data']) ? $result['data'] : [];
        return ServiceResult::ok(array_map(fn($d) => [
            'domain'      => $d['domain_name'] ?? $d['domain'] ?? '',
            'description' => $d['description'] ?? '',
            'mailboxes'   => $d['mboxes_in_domain'] ?? 0,
        ], $domains));
    }

    public function createMailbox(array $data): ServiceResult
    {
        $errors = $this->validateCreateData($data);
        if (! empty($errors)) {
            return ServiceResult::fail($errors);
        }

        $payload = [
            'local_part' => trim($data['local_part']),
            'domain'     => trim($data['domain']),
            'name'       => trim($data['name']),
            'password'   => $data['password'],
            'password2'  => $data['password2'],
            'quota'      => (int) ($data['quota'] ?? $this->settings->get('default_quota_mb', '1024')),
            'active'     => isset($data['active']) && $data['active'] ? 1 : 0,
        ];

        $result = $this->api()->addMailbox($payload);

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        $email = $payload['local_part'] . '@' . $payload['domain'];
        log_message('info', "[Mailboxes] Mailbox created: {$email} by user_id=" . session()->get('user_id'));

        return ServiceResult::ok(null, "Buzón {$email} creado correctamente.");
    }

    public function editMailbox(string $email, array $data): ServiceResult
    {
        $attrs = [];

        if (isset($data['name'])) {
            $attrs['name'] = trim($data['name']);
        }
        if (isset($data['quota'])) {
            $attrs['quota'] = (int) $data['quota'];
        }
        if (! empty($data['password']) && ! empty($data['password2'])) {
            if ($data['password'] !== $data['password2']) {
                return ServiceResult::fail(['password' => 'Las contraseñas no coinciden.']);
            }
            $attrs['password']  = $data['password'];
            $attrs['password2'] = $data['password2'];
        }
        if (array_key_exists('active', $data)) {
            $attrs['active'] = $data['active'] ? 1 : 0;
        }

        if (empty($attrs)) {
            return ServiceResult::fail('No hay cambios para guardar.');
        }

        $result = $this->api()->editMailbox([$email], $attrs);

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        log_message('info', "[Mailboxes] Mailbox edited: {$email} by user_id=" . session()->get('user_id'));

        return ServiceResult::ok(null, "Buzón {$email} actualizado correctamente.");
    }

    public function deleteMailboxes(array $emails): ServiceResult
    {
        if (empty($emails)) {
            return ServiceResult::fail('Selecciona al menos un buzón para eliminar.');
        }

        $result = $this->api()->deleteMailboxes($emails);

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        $count = count($emails);
        log_message('info', "[Mailboxes] Deleted {$count} mailbox(es): " . implode(', ', $emails) . ' by user_id=' . session()->get('user_id'));

        return ServiceResult::ok(null, "{$count} buzón(es) eliminado(s) correctamente.");
    }

    public function toggleMailbox(string $email, bool $active): ServiceResult
    {
        $result = $this->api()->editMailbox([$email], ['active' => $active ? 1 : 0]);

        if (! $result['success']) {
            return ServiceResult::fail($result['error']);
        }

        $state = $active ? 'activado' : 'desactivado';
        log_message('info', "[Mailboxes] Mailbox {$state}: {$email} by user_id=" . session()->get('user_id'));

        return ServiceResult::ok(['active' => $active], "Buzón {$email} {$state}.");
    }

    // -----------------------------------------------------------------------
    // CSV export
    // -----------------------------------------------------------------------

    public function exportCsv(array $mailboxes): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Email', 'Nombre', 'Dominio', 'Cuota (MB)', 'Usado (MB)', 'Uso %', 'Mensajes', 'Estado', 'Último login']);

        foreach ($mailboxes as $m) {
            fputcsv($handle, [
                $m['username'],
                $m['name'],
                $m['domain'],
                round($m['quota'] / 1024 / 1024, 2),
                round($m['quota_used'] / 1024 / 1024, 2),
                $m['quota_percent'],
                $m['messages'],
                $m['active'] ? 'Activo' : 'Inactivo',
                $m['last_imap_login'] ? date('d/m/Y H:i', (int) $m['last_imap_login']) : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    // -----------------------------------------------------------------------
    // Normalization
    // -----------------------------------------------------------------------

    private function normalizeMailboxes(array $raw): array
    {
        return array_map([$this, 'normalizeMailbox'], $raw);
    }

    private function normalizeMailbox(array $m): array
    {
        $quota     = (int) ($m['quota'] ?? 0);
        $quotaUsed = (int) ($m['quota_used'] ?? 0);
        $percent   = $quota > 0 ? round(($quotaUsed / $quota) * 100, 1) : 0;

        return [
            'username'        => $m['username'] ?? '',
            'name'            => $m['name'] ?? '',
            'domain'          => $m['domain'] ?? '',
            'quota'           => $quota,
            'quota_used'      => $quotaUsed,
            'quota_percent'   => $percent,
            'messages'        => (int) ($m['messages'] ?? 0),
            'active'          => (bool) ($m['active'] ?? false),
            'last_imap_login' => $m['last_imap_login'] ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    private function validateCreateData(array $data): array
    {
        $errors = [];

        if (empty(trim($data['local_part'] ?? ''))) {
            $errors['local_part'] = 'El usuario local es obligatorio.';
        } elseif (! preg_match('/^[a-zA-Z0-9._+\-]+$/', $data['local_part'])) {
            $errors['local_part'] = 'El usuario local contiene caracteres inválidos.';
        }

        if (empty(trim($data['domain'] ?? ''))) {
            $errors['domain'] = 'El dominio es obligatorio.';
        }

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'El nombre completo es obligatorio.';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($data['password'] !== ($data['password2'] ?? '')) {
            $errors['password'] = 'Las contraseñas no coinciden.';
        }

        if (isset($data['quota']) && ((int) $data['quota'] < 0)) {
            $errors['quota'] = 'La cuota debe ser un número positivo.';
        }

        return $errors;
    }
}
