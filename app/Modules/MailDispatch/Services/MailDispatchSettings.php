<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\MailDispatchSettingsModel;
use App\Modules\MailDispatch\Models\RuleModel;

/**
 * Typed accessor over maildispatch_settings. The SuperAdmin edits these; the
 * sync command and the operational area consume them (Graph credentials,
 * mailbox, sync control, SLA thresholds). The client secret is write-only from
 * the UI — never echoed back in clear.
 */
class MailDispatchSettings
{
    /** Placeholder posted back by the form when the secret is left untouched. */
    public const SECRET_MASK = '********';

    public function __construct(
        private MailDispatchSettingsModel $model
    ) {}

    // -----------------------------------------------------------------------
    // Reads
    // -----------------------------------------------------------------------

    public function all(): array
    {
        return $this->model->getAll();
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->model->get($key, $default);
    }

    public function tenantId(): string     { return $this->model->get('graph_tenant_id'); }
    public function clientId(): string     { return $this->model->get('graph_client_id'); }
    public function clientSecret(): string { return $this->model->get('graph_client_secret'); }
    public function mailbox(): string      { return trim($this->model->get('mailbox_address')); }

    public function hasSecret(): bool { return $this->model->hasSecret('graph_client_secret'); }

    // -----------------------------------------------------------------------
    // Provider selection
    // -----------------------------------------------------------------------

    /** Active backend: 'graph' (Microsoft Graph) or 'imap'. Defaults to graph. */
    public function provider(): string
    {
        $p = strtolower(trim($this->model->get('provider', 'graph')));
        return in_array($p, ['graph', 'imap'], true) ? $p : 'graph';
    }

    public function isImap(): bool  { return $this->provider() === 'imap'; }
    public function isGraph(): bool { return $this->provider() === 'graph'; }

    /**
     * Forward mode: the mailbox receives forwarded copies, so the real requester
     * is the De:/From: block inside the body, not the SMTP sender.
     */
    public function treatAsForwards(): bool
    {
        return $this->model->get('treat_as_forwards', '0') === '1';
    }

    // -----------------------------------------------------------------------
    // IMAP (read side)
    // -----------------------------------------------------------------------

    public function imapHost(): string     { return trim($this->model->get('imap_host')); }
    public function imapPort(): int        { $n = (int) $this->model->get('imap_port', '993'); return $n > 0 ? $n : 993; }
    public function imapEncryption(): string { $e = strtolower(trim($this->model->get('imap_encryption', 'ssl'))); return in_array($e, ['ssl', 'tls', 'none'], true) ? $e : 'ssl'; }
    public function imapValidateCert(): bool { return $this->model->get('imap_validate_cert', '1') === '1'; }
    public function imapUsername(): string { return trim($this->model->get('imap_username')); }
    public function imapPassword(): string { return $this->model->get('imap_password'); }
    public function imapFolder(): string   { $f = trim($this->model->get('imap_folder', 'INBOX')); return $f !== '' ? $f : 'INBOX'; }

    public function hasImapPassword(): bool { return $this->model->hasSecret('imap_password'); }

    // -----------------------------------------------------------------------
    // SMTP (send side — reply from Nexus in IMAP mode)
    // -----------------------------------------------------------------------

    public function smtpHost(): string     { return trim($this->model->get('smtp_host')); }
    public function smtpPort(): int        { $n = (int) $this->model->get('smtp_port', '587'); return $n > 0 ? $n : 587; }
    public function smtpEncryption(): string { $e = strtolower(trim($this->model->get('smtp_encryption', 'tls'))); return in_array($e, ['ssl', 'tls', 'none'], true) ? $e : 'tls'; }
    public function smtpUsername(): string { return trim($this->model->get('smtp_username')); }
    public function smtpPassword(): string { return $this->model->get('smtp_password'); }
    public function smtpFromEmail(): string { $e = trim($this->model->get('smtp_from_email')); return $e !== '' ? $e : $this->mailbox(); }
    public function smtpFromName(): string { $n = trim($this->model->get('smtp_from_name')); return $n !== '' ? $n : 'Mesa de ayuda'; }

    public function hasSmtpPassword(): bool { return $this->model->hasSecret('smtp_password'); }

    /** True when SMTP is fully configured (host + username + a stored password). */
    public function isSmtpConfigured(): bool
    {
        return $this->smtpHost() !== '' && $this->smtpUsername() !== '' && $this->hasSmtpPassword();
    }

    public function isSyncEnabled(): bool { return $this->model->get('sync_enabled', '0') === '1'; }

    /**
     * Reply-from-Nexus master switch. In IMAP mode it additionally requires SMTP
     * to be configured, since IMAP is read-only and sending goes through SMTP.
     */
    public function isSendEnabled(): bool
    {
        if ($this->model->get('send_from_nexus_enabled', '0') !== '1') {
            return false;
        }
        return $this->isImap() ? $this->isSmtpConfigured() : true;
    }

    public function pageSize(): int
    {
        $n = (int) $this->model->get('sync_page_size', '50');
        return $n > 0 ? min($n, 999) : 50;
    }

    public function slaUnassignedMinutes(): int
    {
        return max(0, (int) $this->model->get('sla_unassigned_minutes', '30'));
    }

    public function slaFirstResponseMinutes(): int
    {
        return max(0, (int) $this->model->get('sla_first_response_minutes', '120'));
    }

    /**
     * True when the active provider is fully configured. Graph needs tenant,
     * client id, secret and mailbox; IMAP needs host, username, a stored
     * password and the logical mailbox address (used for direction detection).
     */
    public function isConfigured(): bool
    {
        if ($this->isImap()) {
            return $this->imapHost() !== ''
                && $this->imapUsername() !== ''
                && $this->hasImapPassword()
                && $this->mailbox() !== '';
        }

        return $this->tenantId() !== ''
            && $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->mailbox() !== '';
    }

    // -----------------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------------

    /**
     * Persists the settings form. The secret is only overwritten when a real
     * value (not the mask, not empty) is submitted.
     */
    public function save(array $post): ServiceResult
    {
        $tenant   = trim((string) ($post['graph_tenant_id'] ?? ''));
        $clientId = trim((string) ($post['graph_client_id'] ?? ''));
        $mailbox  = trim((string) ($post['mailbox_address'] ?? ''));

        $provider = strtolower(trim((string) ($post['provider'] ?? 'graph')));
        $provider = in_array($provider, ['graph', 'imap'], true) ? $provider : 'graph';

        if ($mailbox !== '' && ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('La dirección del buzón no es un correo válido.');
        }

        $smtpFrom = trim((string) ($post['smtp_from_email'] ?? ''));
        if ($smtpFrom !== '' && ! filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('El remitente SMTP no es un correo válido.');
        }

        $data = [
            'provider'                    => $provider,
            'graph_tenant_id'             => $tenant,
            'graph_client_id'             => $clientId,
            'mailbox_address'             => $mailbox,
            'sync_enabled'                => isset($post['sync_enabled']) ? '1' : '0',
            'sync_page_size'              => (string) max(1, (int) ($post['sync_page_size'] ?? 50)),
            'sla_unassigned_minutes'      => (string) max(0, (int) ($post['sla_unassigned_minutes'] ?? 30)),
            'sla_first_response_minutes'  => (string) max(0, (int) ($post['sla_first_response_minutes'] ?? 120)),
            'send_from_nexus_enabled'     => isset($post['send_from_nexus_enabled']) ? '1' : '0',

            // --- IMAP (read) ---
            'imap_host'                   => trim((string) ($post['imap_host'] ?? '')),
            'imap_port'                   => (string) max(1, (int) ($post['imap_port'] ?? 993)),
            'imap_encryption'             => $this->normalizeCrypto((string) ($post['imap_encryption'] ?? 'ssl'), 'ssl'),
            'imap_validate_cert'          => isset($post['imap_validate_cert']) ? '1' : '0',
            'imap_username'               => trim((string) ($post['imap_username'] ?? '')),
            'imap_folder'                 => trim((string) ($post['imap_folder'] ?? 'INBOX')) ?: 'INBOX',
            'treat_as_forwards'           => isset($post['treat_as_forwards']) ? '1' : '0',

            // --- SMTP (send) ---
            'smtp_host'                   => trim((string) ($post['smtp_host'] ?? '')),
            'smtp_port'                   => (string) max(1, (int) ($post['smtp_port'] ?? 587)),
            'smtp_encryption'             => $this->normalizeCrypto((string) ($post['smtp_encryption'] ?? 'tls'), 'tls'),
            'smtp_username'               => trim((string) ($post['smtp_username'] ?? '')),
            'smtp_from_email'             => $smtpFrom,
            'smtp_from_name'              => trim((string) ($post['smtp_from_name'] ?? '')),
        ];

        // Only overwrite secrets when a fresh value (not the mask, not empty) is provided.
        foreach ([
            'graph_client_secret' => 'graph_client_secret',
            'imap_password'       => 'imap_password',
            'smtp_password'       => 'smtp_password',
        ] as $field => $key) {
            $val = (string) ($post[$field] ?? '');
            if ($val !== '' && $val !== self::SECRET_MASK) {
                $data[$key] = $val;
            }
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración de Despacho guardada.');
    }

    /** Clamps an encryption choice to the allowed set. */
    private function normalizeCrypto(string $value, string $default): string
    {
        $v = strtolower(trim($value));
        return in_array($v, ['ssl', 'tls', 'none'], true) ? $v : $default;
    }

    /**
     * Saves the agent roster from the admin form. $post['agent'] is a map of
     * user_id => ['active' => '1'?, 'dispatcher' => '1'?]. Users not present are
     * deactivated (not deleted) to preserve history/FKs.
     */
    public function saveAgents(array $post): ServiceResult
    {
        $rows   = (array) ($post['agent'] ?? []);
        $agents = new AgentModel();
        $now    = date('Y-m-d H:i:s');

        // Deactivate everyone first; re-activate the checked ones below.
        $agents->set('is_active', 0)->set('is_dispatcher', 0)->set('updated_at', $now)->where('id >', 0)->update();

        foreach ($rows as $userId => $flags) {
            $userId = (int) $userId;
            if ($userId <= 0 || empty($flags['active'])) {
                continue;
            }
            $isDispatcher = ! empty($flags['dispatcher']) ? 1 : 0;
            $existing     = $agents->findByUser($userId);

            if ($existing) {
                $agents->update((int) $existing['id'], [
                    'is_active'     => 1,
                    'is_dispatcher' => $isDispatcher,
                ]);
            } else {
                $agents->insert([
                    'user_id'       => $userId,
                    'is_active'     => 1,
                    'is_dispatcher' => $isDispatcher,
                ]);
            }
        }

        return ServiceResult::ok(null, 'Agentes de despacho actualizados.');
    }

    /**
     * Saves the disposition catalog. $post['disposition'] is a list of rows with
     * id (optional), name, requires_folio, is_active. Blank names are dropped.
     */
    public function saveDispositions(array $post): ServiceResult
    {
        $rows  = (array) ($post['disposition'] ?? []);
        $model = new DispositionModel();
        $order = 1;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $data = [
                'name'           => $name,
                'requires_folio' => ! empty($row['requires_folio']) ? 1 : 0,
                'is_active'      => ! empty($row['is_active']) ? 1 : 0,
                'sort_order'     => $order++,
            ];
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $model->update($id, $data);
            } else {
                $model->insert($data);
            }
        }

        return ServiceResult::ok(null, 'Catálogo de disposiciones actualizado.');
    }

    /**
     * Upserts the auto-triage rules. Each row needs a name and at least one of
     * sender/subject patterns; rows without a name are skipped. Rules are never
     * hard-deleted (conversations reference them) — deactivate to disable.
     */
    public function saveRules(array $post): ServiceResult
    {
        $rows  = (array) ($post['rule'] ?? []);
        $model = new RuleModel();
        $order = 1;

        foreach ($rows as $row) {
            $name    = trim((string) ($row['name'] ?? ''));
            $sender  = trim((string) ($row['sender_pattern'] ?? ''));
            $subject = trim((string) ($row['subject_pattern'] ?? ''));
            $id      = (int) ($row['id'] ?? 0);

            // A blank row (no name, no patterns) is just an unused add-slot.
            if ($name === '' && $sender === '' && $subject === '' && $id === 0) {
                continue;
            }
            if ($name === '') {
                return ServiceResult::fail('Cada regla necesita un nombre.');
            }
            if ($sender === '' && $subject === '') {
                return ServiceResult::fail("La regla «{$name}» necesita al menos un patrón (remitente o asunto).");
            }

            $data = [
                'name'            => $name,
                'sender_pattern'  => $sender,
                'subject_pattern' => $subject,
                'is_active'       => ! empty($row['is_active']) ? 1 : 0,
                'sort_order'      => $order++,
            ];
            if ($id > 0) {
                $model->update($id, $data);
            } else {
                $model->insert($data);
            }
        }

        return ServiceResult::ok(null, 'Reglas de autoarchivo actualizadas.');
    }
}
