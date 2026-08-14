<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\BusinessExceptionModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\AutogenRuleModel;
use App\Modules\MailDispatch\Models\AutogenWhitelistModel;
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

    /** Prompt por defecto del extractor de IA (Fase 2). */
    private const DEFAULT_AI_PROMPT = 'Eres un asistente que extrae datos de un correo para crear un ticket de soporte. '
        . 'Devuelve SIEMPRE la herramienta extract_ticket con los campos solicitados (usa cadena vacía si un dato no aparece en el correo), '
        . 'un confidence de 0 a 1 según qué tan seguro estás, e is_request=true solo si el correo realmente solicita crear un ticket. No inventes datos.';

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

    /**
     * Whether replies reuse the platform's Core SMTP (Configuración › SMTP)
     * instead of a mailbox-specific SMTP. Default on: less duplicated config.
     */
    public function smtpUseCore(): bool { return $this->model->get('smtp_use_core', '1') === '1'; }

    /**
     * The effective SMTP config for sending replies, resolving the "use Core"
     * toggle. Keys: host, port, crypto ('ssl'|'tls'|''), user, pass, from_email,
     * from_name. When reusing Core, values come from AppSettingsService (Core's
     * smtp_* keys, password already decrypted); the From falls back to the
     * mailbox address / a generic name when Core leaves them blank.
     */
    public function effectiveSmtp(): array
    {
        if ($this->smtpUseCore()) {
            $c      = service('appSettings')->getSmtp();
            $crypto = strtolower(trim((string) ($c['smtp_crypto'] ?? '')));
            return [
                'host'       => trim((string) ($c['smtp_host'] ?? '')),
                'port'       => ((int) ($c['smtp_port'] ?? 587)) ?: 587,
                'crypto'     => in_array($crypto, ['ssl', 'tls'], true) ? $crypto : '',
                'user'       => trim((string) ($c['smtp_user'] ?? '')),
                'pass'       => (string) ($c['smtp_password'] ?? ''),
                'from_email' => trim((string) ($c['smtp_from_email'] ?? '')) ?: $this->mailbox(),
                'from_name'  => trim((string) ($c['smtp_from_name'] ?? '')) ?: 'Mesa de ayuda',
            ];
        }

        $enc = $this->smtpEncryption();
        return [
            'host'       => $this->smtpHost(),
            'port'       => $this->smtpPort(),
            'crypto'     => $enc === 'none' ? '' : $enc,
            'user'       => $this->smtpUsername(),
            'pass'       => $this->smtpPassword(),
            'from_email' => $this->smtpFromEmail(),
            'from_name'  => $this->smtpFromName(),
        ];
    }

    /**
     * True when the SMTP used for replies is fully configured. In Core mode this
     * defers to the platform SMTP; otherwise it checks the module's own fields.
     */
    public function isSmtpConfigured(): bool
    {
        if ($this->smtpUseCore()) {
            return service('appSettings')->isSmtpConfigured();
        }
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

    /**
     * Import cutoff (app timezone), 'Y-m-d H:i:s', or '' when no cutoff is set.
     * Only the initial/--full pull is bounded by this; once the delta/UID cursor
     * has advanced it simply moves forward. Change it and re-run --full (or purge
     * the operational data) to reapply.
     */
    public function syncSince(): string
    {
        $raw = trim($this->model->get('sync_since', ''));
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : '';
    }

    /** The cutoff as a UTC ISO-8601 instant for Graph's $filter, or '' if unset. */
    public function syncSinceUtcIso(): string
    {
        $local = $this->syncSince();
        if ($local === '') {
            return '';
        }
        try {
            $dt = new \DateTime($local, new \DateTimeZone(app_timezone()));
            $dt->setTimezone(new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return '';
        }
    }

    /** The cutoff as a Unix timestamp, or null when no cutoff is set. */
    public function syncSinceTimestamp(): ?int
    {
        $local = $this->syncSince();
        if ($local === '') {
            return null;
        }
        $ts = strtotime($local);
        return $ts !== false ? $ts : null;
    }

    public function slaUnassignedMinutes(): int
    {
        return max(0, (int) $this->model->get('sla_unassigned_minutes', '30'));
    }

    public function slaFirstResponseMinutes(): int
    {
        return max(0, (int) $this->model->get('sla_first_response_minutes', '120'));
    }

    // -----------------------------------------------------------------------
    // Horario de servicio (reloj del SLA)
    // -----------------------------------------------------------------------

    /**
     * Whether the SLA clock only runs inside the service schedule. Off means
     * plain wall-clock minutes, the behaviour that predates the calendar.
     */
    public function businessHoursEnabled(): bool
    {
        return $this->model->get('business_hours_enabled', '0') === '1';
    }

    /**
     * Raw weekly schedule as stored: weekday (1 = Monday … 7 = Sunday) =>
     * ['closed' => bool, 'open' => 'HH:MM', 'close' => 'HH:MM']. Normalizing and
     * filling gaps is BusinessCalendar's job.
     */
    public function businessHoursSchedule(): array
    {
        $decoded = json_decode($this->model->get('business_hours_schedule', ''), true);
        return is_array($decoded) ? $decoded : [];
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
    // Autogestión (auto-crear tickets GLPI desde correo)
    // -----------------------------------------------------------------------

    public function autogestionEnabled(): bool { return $this->model->get('autogestion_enabled', '0') === '1'; }
    public function aiDetectionEnabled(): bool { return $this->model->get('autogestion_ai_enabled', '0') === '1'; }

    public function autogenDefaultTicketType(): string
    {
        $t = strtoupper(trim($this->model->get('autogen_default_ticket_type', 'INCIDENCIA')));
        return in_array($t, ['INCIDENCIA', 'REQUERIMIENTO'], true) ? $t : 'INCIDENCIA';
    }

    public function autogenDefaultCategoryId(): int      { return max(0, (int) $this->model->get('autogen_default_category_id', '0')); }
    public function autogenDefaultEntitiesId(): int      { return max(0, (int) $this->model->get('autogen_default_entities_id', '0')); }
    public function autogenDefaultRequesterUserId(): int { return max(0, (int) $this->model->get('autogen_default_requester_user_id', '0')); }
    public function autogenDefaultRequestSourceId(): int { return max(0, (int) $this->model->get('autogen_default_request_source_id', '0')); }
    public function autogenDefaultContainerIds(): string { return trim($this->model->get('autogen_default_container_ids', '')); }
    public function autogenSystemUserId(): int           { return max(0, (int) $this->model->get('autogen_system_user_id', '0')); }
    public function autogenRateLimitPerHour(): int       { return max(0, (int) $this->model->get('autogen_rate_limit_per_hour', '0')); }
    public function autogenMaxAttempts(): int            { $n = (int) $this->model->get('autogen_max_attempts', '3'); return $n > 0 ? min($n, 10) : 3; }

    // --- IA (Fase 2): reusa llave/modelo de ServiceDesk; lo demás es propio ---
    public function autogenAiSystemPrompt(): string
    {
        $p = trim($this->model->get('autogen_ai_system_prompt', ''));
        return $p !== '' ? $p : self::DEFAULT_AI_PROMPT;
    }
    public function autogenAiMaxTokens(): int   { $n = (int) $this->model->get('autogen_ai_max_tokens', '1024'); return $n > 0 ? min($n, 4096) : 1024; }
    public function autogenAiConfidence(): float { $v = (float) $this->model->get('autogen_ai_confidence', '0.6'); return ($v > 0 && $v <= 1) ? $v : 0.6; }
    public function autogenAiApiKey(): string   { return service('serviceDeskSettings')->aiApiKey(); }
    public function autogenAiModel(): string    { return service('serviceDeskSettings')->aiModel(); }
    public function autogenAiReady(): bool      { return $this->aiDetectionEnabled() && $this->autogenAiApiKey() !== ''; }

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
            'sync_since'                  => $this->normalizeDateTime((string) ($post['sync_since'] ?? '')),
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
            'smtp_use_core'               => (string) ($post['smtp_mode'] ?? 'core') === 'own' ? '0' : '1',
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

    /** Normalizes a form datetime (e.g. 'Y-m-dTH:i') to 'Y-m-d H:i:s'; '' if blank/invalid. */
    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : '';
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

    /**
     * Saves the service schedule that drives the SLA clock: the master toggle,
     * the seven weekday windows, and the dated exceptions.
     *
     * $post['day'] is a map 1..7 => ['closed' => '1'?, 'open' => 'HH:MM',
     * 'close' => 'HH:MM']; $post['exception'] is a list of rows with date,
     * closed, open, close and note. Exceptions are rebuilt wholesale (no FK
     * references them), so removing a row from the form removes the holiday.
     */
    public function saveSchedule(array $post): ServiceResult
    {
        $days     = (array) ($post['day'] ?? []);
        $schedule = [];

        foreach (BusinessCalendar::DAY_LABELS as $dow => $label) {
            $row    = (array) ($days[$dow] ?? []);
            $closed = ! empty($row['closed']);
            $open   = BusinessCalendar::normalizeTime((string) ($row['open'] ?? ''), '');
            $close  = BusinessCalendar::normalizeTime((string) ($row['close'] ?? ''), '');

            if (! $closed) {
                if ($open === '' || $close === '') {
                    return ServiceResult::fail("{$label}: indica la hora de apertura y la de cierre, o marca el día como cerrado.");
                }
                if ($close <= $open) {
                    return ServiceResult::fail("{$label}: la hora de cierre debe ser posterior a la de apertura.");
                }
            }

            $schedule[(string) $dow] = [
                'closed' => $closed,
                'open'   => $open !== '' ? $open : '09:00',
                'close'  => $close !== '' ? $close : '18:00',
            ];
        }

        $enabled = ! empty($post['business_hours_enabled']);
        $anyOpen = false;
        foreach ($schedule as $row) {
            $anyOpen = $anyOpen || ! $row['closed'];
        }
        if ($enabled && ! $anyOpen) {
            return ServiceResult::fail('Con todos los días cerrados el reloj del SLA nunca avanzaría: deja al menos un día abierto.');
        }

        // Validate the exceptions before touching anything, so a bad row leaves
        // the stored calendar intact.
        $exceptions = [];
        foreach ((array) ($post['exception'] ?? []) as $row) {
            $date = trim((string) ($row['date'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            if ($date === '') {
                // An empty add-slot; only complain if the operator typed a note.
                if ($note !== '') {
                    return ServiceResult::fail("La excepción «{$note}» necesita una fecha.");
                }
                continue;
            }

            $ts = strtotime($date);
            if ($ts === false) {
                return ServiceResult::fail("La fecha «{$date}» de una excepción no es válida.");
            }
            $date = date('Y-m-d', $ts);

            if (isset($exceptions[$date])) {
                return ServiceResult::fail("Hay dos excepciones para el {$date}: deja solo una.");
            }

            $isClosed = ! empty($row['closed']);
            $open     = BusinessCalendar::normalizeTime((string) ($row['open'] ?? ''), '');
            $close    = BusinessCalendar::normalizeTime((string) ($row['close'] ?? ''), '');

            if (! $isClosed) {
                if ($open === '' || $close === '') {
                    return ServiceResult::fail("La excepción del {$date} es de horario reducido: indica apertura y cierre, o márcala como cerrada.");
                }
                if ($close <= $open) {
                    return ServiceResult::fail("La excepción del {$date}: la hora de cierre debe ser posterior a la de apertura.");
                }
            }

            $exceptions[$date] = [
                'exception_date' => $date,
                'is_closed'      => $isClosed ? 1 : 0,
                'open_time'      => $isClosed || $open === '' ? null : $open . ':00',
                'close_time'     => $isClosed || $close === '' ? null : $close . ':00',
                'note'           => $note !== '' ? mb_substr($note, 0, 160) : null,
            ];
        }

        $this->model->setMany([
            'business_hours_enabled'  => $enabled ? '1' : '0',
            'business_hours_schedule' => json_encode($schedule, JSON_UNESCAPED_UNICODE),
        ]);

        $exModel = new BusinessExceptionModel();
        $exModel->where('id >', 0)->delete();
        foreach ($exceptions as $ex) {
            $exModel->insert($ex);
        }

        return ServiceResult::ok(null, 'Horario de servicio guardado.');
    }

    // -----------------------------------------------------------------------
    // Autogestión: settings globales y editor de reglas
    // -----------------------------------------------------------------------

    /** Guarda el toggle y los defaults globales de autogestión. */
    public function saveAutogen(array $post): ServiceResult
    {
        $type = strtoupper(trim((string) ($post['autogen_default_ticket_type'] ?? 'INCIDENCIA')));
        $type = in_array($type, ['INCIDENCIA', 'REQUERIMIENTO'], true) ? $type : 'INCIDENCIA';

        $this->model->setMany([
            'autogestion_enabled'               => isset($post['autogestion_enabled']) ? '1' : '0',
            'autogestion_ai_enabled'            => isset($post['autogestion_ai_enabled']) ? '1' : '0',
            'autogen_default_ticket_type'       => $type,
            'autogen_default_category_id'       => (string) max(0, (int) ($post['autogen_default_category_id'] ?? 0)),
            'autogen_default_entities_id'       => (string) max(0, (int) ($post['autogen_default_entities_id'] ?? 0)),
            'autogen_default_requester_user_id' => (string) max(0, (int) ($post['autogen_default_requester_user_id'] ?? 0)),
            'autogen_default_request_source_id' => (string) max(0, (int) ($post['autogen_default_request_source_id'] ?? 0)),
            'autogen_default_container_ids'     => trim((string) ($post['autogen_default_container_ids'] ?? '')),
            'autogen_system_user_id'            => (string) max(0, (int) ($post['autogen_system_user_id'] ?? 0)),
            'autogen_rate_limit_per_hour'       => (string) max(0, (int) ($post['autogen_rate_limit_per_hour'] ?? 0)),
            'autogen_max_attempts'              => (string) max(1, (int) ($post['autogen_max_attempts'] ?? 3)),
            'autogen_ai_system_prompt'          => trim((string) ($post['autogen_ai_system_prompt'] ?? '')),
            'autogen_ai_max_tokens'             => (string) max(256, (int) ($post['autogen_ai_max_tokens'] ?? 1024)),
            'autogen_ai_confidence'             => (string) (((float) ($post['autogen_ai_confidence'] ?? 0.6) > 0 && (float) ($post['autogen_ai_confidence'] ?? 0.6) <= 1) ? (float) $post['autogen_ai_confidence'] : 0.6),
        ]);

        return ServiceResult::ok(null, 'Configuración de autogestión guardada.');
    }

    /**
     * Upserts de reglas de autogestión + su lista blanca. $post['agrule'] es una
     * lista de filas. La lista blanca (textarea) y el field_map (textarea) se
     * parsean por línea. Las reglas no se borran (conversaciones las referencian).
     */
    public function saveAutogenRules(array $post): ServiceResult
    {
        $rows      = (array) ($post['agrule'] ?? []);
        $ruleModel = new AutogenRuleModel();
        $wlModel   = new AutogenWhitelistModel();
        $order     = 1;

        foreach ($rows as $row) {
            $name    = trim((string) ($row['name'] ?? ''));
            $subject = trim((string) ($row['subject_pattern'] ?? ''));
            $id      = (int) ($row['id'] ?? 0);

            if ($name === '' && $subject === '' && $id === 0) {
                continue;
            }
            if ($name === '') {
                return ServiceResult::fail('Cada regla de autogestión necesita un nombre.');
            }
            if ($subject === '') {
                return ServiceResult::fail("La regla «{$name}» necesita un patrón de asunto.");
            }

            $type = strtoupper(trim((string) ($row['glpi_ticket_type'] ?? '')));
            $type = in_array($type, ['INCIDENCIA', 'REQUERIMIENTO'], true) ? $type : null;

            $data = [
                'name'                   => $name,
                'is_active'              => ! empty($row['is_active']) ? 1 : 0,
                'sort_order'             => $order++,
                'subject_pattern'        => $subject,
                'subject_match_mode'     => ($row['subject_match_mode'] ?? 'contains') === 'exact' ? 'exact' : 'contains',
                'recipient_pattern'      => trim((string) ($row['recipient_pattern'] ?? '')),
                'glpi_ticket_type'       => $type,
                'glpi_category_id'       => ($n = (int) ($row['glpi_category_id'] ?? 0)) > 0 ? $n : null,
                'glpi_entities_id'       => ($n = (int) ($row['glpi_entities_id'] ?? 0)) > 0 ? $n : null,
                'glpi_requester_user_id' => ($n = (int) ($row['glpi_requester_user_id'] ?? 0)) > 0 ? $n : null,
                'request_source_id'      => ($n = (int) ($row['request_source_id'] ?? 0)) > 0 ? $n : null,
                'container_ids'          => trim((string) ($row['container_ids'] ?? '')),
                'field_map'              => $this->parseFieldMap((string) ($row['field_map'] ?? '')),
                'reply_subject'          => trim((string) ($row['reply_subject'] ?? '')),
                'reply_body'             => (string) ($row['reply_body'] ?? ''),
                'ai_enabled'             => ! empty($row['ai_enabled']) ? 1 : 0,
            ];

            if ($id > 0) {
                $ruleModel->update($id, $data);
            } else {
                $ruleModel->insert($data);
                $id = (int) $ruleModel->getInsertID();
            }

            // Reconstruye la lista blanca de la regla desde el textarea.
            $wlModel->where('rule_id', $id)->delete();
            foreach (preg_split('/\r\n|\r|\n/', (string) ($row['whitelist'] ?? '')) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $t = 'sender';
                $v = $line;
                if (str_contains($line, ':')) {
                    [$t, $v] = explode(':', $line, 2);
                    $t = trim(strtolower($t));
                    $v = trim($v);
                }
                $t = in_array($t, ['sender', 'recipient'], true) ? $t : 'sender';
                if ($v !== '') {
                    $wlModel->insert(['rule_id' => $id, 'type' => $t, 'value' => $v, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }

        return ServiceResult::ok(null, 'Reglas de autogestión actualizadas.');
    }

    /** Convierte el textarea "Etiqueta | target | requerido" en el JSON field_map. */
    private function parseFieldMap(string $text): string
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] ?? '';
            if ($label === '') {
                continue;
            }
            $target = $parts[1] ?? 'description';
            if (! in_array($target, ['title', 'description', 'ignore'], true)) {
                $target = 'description';
            }
            $required = isset($parts[2]) && in_array(strtolower($parts[2]), ['1', 'si', 'sí', 'true', 'x', 'yes'], true);
            $out[] = ['label' => $label, 'target' => $target, 'required' => $required];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}
