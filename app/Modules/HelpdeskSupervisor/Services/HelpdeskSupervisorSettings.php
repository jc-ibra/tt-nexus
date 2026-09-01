<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\HelpdeskSupervisor\Models\HelpdeskSupervisorSettingsModel;
use App\Modules\Provisioning\Services\CredentialCipher;

/**
 * Typed accessor over helpdesk_supervisor_settings. The SuperAdmin edits these;
 * the audit engine consumes them (GLPI target, abandonment threshold, opening
 * date tolerance).
 */
class HelpdeskSupervisorSettings
{
    private ?CredentialCipher $cipher = null;

    public function __construct(
        private HelpdeskSupervisorSettingsModel $model,
    ) {}

    public function all(): array
    {
        return $this->model->getAll();
    }

    // ------------------------------------------------------------------
    // GLPI connection
    // ------------------------------------------------------------------

    /** When true, audit reuses Provisioning's GLPI connection instead of its own params. */
    public function reuseProvisioningConnection(): bool
    {
        return $this->model->get('glpi_db_reuse_provisioning', '1') === '1';
    }

    public function glpiDbHost(): string
    {
        return trim($this->model->get('glpi_db_host', ''));
    }

    public function glpiDbPort(): int
    {
        return max(1, (int) $this->model->get('glpi_db_port', '3306'));
    }

    public function glpiDbName(): string
    {
        return trim($this->model->get('glpi_db_name', ''));
    }

    public function glpiDbUser(): string
    {
        return trim($this->model->get('glpi_db_user', ''));
    }

    /** Decrypted GLPI DB password ('' when unset or the cipher is unavailable). */
    public function glpiDbPassword(): string
    {
        return $this->cipher()->decrypt($this->model->get('glpi_db_password', ''));
    }

    /**
     * Whether an OWN (non-reused) connection has the minimum params filled.
     */
    public function ownConnectionConfigured(): bool
    {
        return $this->glpiDbHost() !== ''
            && $this->glpiDbName() !== ''
            && $this->glpiDbUser() !== '';
    }

    // ------------------------------------------------------------------
    // Audit behavior
    // ------------------------------------------------------------------

    /** Business days without agent activity before an open ticket counts as abandoned (KPI 4). */
    public function businessDaysAbandonment(): int
    {
        return max(1, (int) $this->model->get('business_days_abandonment', '5'));
    }

    /** Tolerance (seconds) between date and date_creation to flag a default opening date. */
    public function openingDateToleranceSeconds(): int
    {
        return max(1, (int) $this->model->get('opening_date_tolerance_sec', '60'));
    }

    public function auditAutoRun(): bool
    {
        return $this->model->get('audit_auto_run', '0') === '1';
    }

    // ------------------------------------------------------------------
    // Live GLPI overview (Resumen)
    // ------------------------------------------------------------------

    /** `all` = every entity; `specific` = filter by overview_entities_id. */
    public function overviewEntitiesMode(): string
    {
        $m = strtolower(trim($this->model->get('overview_entities_mode', 'all')));
        return $m === 'specific' ? 'specific' : 'all';
    }

    public function overviewEntitiesId(): int
    {
        return max(0, (int) $this->model->get('overview_entities_id', '0'));
    }

    public function overviewEntitiesRecursive(): bool
    {
        return $this->model->get('overview_entities_recursive', '1') === '1';
    }

    /** @return int[] GLPI status ids counted as open backlog. */
    public function overviewOpenStatuses(): array
    {
        $ids = $this->parseIdList($this->model->get('overview_open_statuses', '1,2,3,4'));
        return $ids !== [] ? $ids : [1, 2, 3, 4];
    }

    /** @return int[] ticket types (1=Incidencia, 2=Requerimiento); empty = no type filter. */
    public function overviewTicketTypes(): array
    {
        return $this->parseIdList($this->model->get('overview_ticket_types', '1,2'));
    }

    /**
     * Optional root ITIL category ids that scope the overview. Empty = all.
     * When set, only tickets whose category is that root or a descendant count.
     *
     * @return int[]
     */
    public function overviewCategoryRoots(): array
    {
        return $this->parseIdList($this->model->get('overview_category_roots', ''));
    }

    public function overviewTopNCategories(): int
    {
        return max(1, min(50, (int) $this->model->get('overview_top_n_categories', '10')));
    }

    public function overviewTopNSources(): int
    {
        return max(1, min(50, (int) $this->model->get('overview_top_n_sources', '10')));
    }

    public function overviewTopNRequesters(): int
    {
        return max(1, min(100, (int) $this->model->get('overview_top_n_requesters', '15')));
    }

    public function overviewTopNAssignees(): int
    {
        return max(1, min(100, (int) $this->model->get('overview_top_n_assignees', '15')));
    }

    public function overviewCriticalDays(): int
    {
        return max(1, (int) $this->model->get('overview_critical_days', '30'));
    }

    /** Cache TTL in seconds for the live overview (0 = no cache). */
    public function overviewCacheTtl(): int
    {
        return max(0, min(3600, (int) $this->model->get('overview_cache_ttl', '120')));
    }

    /**
     * Persists the Resumen GLPI settings section. Invalidates overview cache so
     * the next page load reflects the new filters immediately.
     */
    public function saveOverview(array $input): ServiceResult
    {
        $mode = strtolower(trim((string) ($input['overview_entities_mode'] ?? 'all')));
        if ($mode !== 'specific') {
            $mode = 'all';
        }

        $statuses = $this->parseIdListFromInput($input['overview_open_statuses'] ?? null);
        if ($statuses === []) {
            return ServiceResult::fail('Selecciona al menos un estatus abierto para el backlog.');
        }

        $types = $this->parseIdListFromInput($input['overview_ticket_types'] ?? null);
        // Empty types = allow both; if checkboxes posted empty we treat as both.
        if ($types === []) {
            $types = [1, 2];
        }

        $data = [
            'overview_entities_mode'      => $mode,
            'overview_entities_id'        => (string) max(0, (int) ($input['overview_entities_id'] ?? 0)),
            'overview_entities_recursive' => ! empty($input['overview_entities_recursive']) ? '1' : '0',
            'overview_open_statuses'      => implode(',', $statuses),
            'overview_ticket_types'       => implode(',', $types),
            'overview_category_roots'     => implode(',', $this->parseIdListFromInput($input['overview_category_roots'] ?? '')),
            'overview_top_n_categories'   => (string) max(1, min(50, (int) ($input['overview_top_n_categories'] ?? 10))),
            'overview_top_n_sources'      => (string) max(1, min(50, (int) ($input['overview_top_n_sources'] ?? 10))),
            'overview_top_n_requesters'   => (string) max(1, min(100, (int) ($input['overview_top_n_requesters'] ?? 15))),
            'overview_top_n_assignees'    => (string) max(1, min(100, (int) ($input['overview_top_n_assignees'] ?? 15))),
            'overview_critical_days'      => (string) max(1, (int) ($input['overview_critical_days'] ?? 30)),
            'overview_cache_ttl'          => (string) max(0, min(3600, (int) ($input['overview_cache_ttl'] ?? 120))),
        ];

        $this->model->setMany($data);
        cache()->delete('helpdesk_supervisor_glpi_overview');

        return ServiceResult::ok(null, 'Configuración del resumen GLPI guardada.');
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    /**
     * Persists the settings form. The GLPI password is stored encrypted and only
     * overwritten when a new value is submitted (blank keeps the current one).
     */
    public function save(array $input): ServiceResult
    {
        $data = [
            'glpi_db_reuse_provisioning' => ! empty($input['glpi_db_reuse_provisioning']) ? '1' : '0',
            'glpi_db_host'               => trim((string) ($input['glpi_db_host'] ?? '')),
            'glpi_db_port'               => (string) max(1, (int) ($input['glpi_db_port'] ?? 3306)),
            'glpi_db_name'               => trim((string) ($input['glpi_db_name'] ?? '')),
            'glpi_db_user'               => trim((string) ($input['glpi_db_user'] ?? '')),
            'business_days_abandonment'  => (string) max(1, (int) ($input['business_days_abandonment'] ?? 5)),
            'opening_date_tolerance_sec' => (string) max(1, (int) ($input['opening_date_tolerance_sec'] ?? 60)),
            'audit_auto_run'             => ! empty($input['audit_auto_run']) ? '1' : '0',
        ];

        $newPass = trim((string) ($input['glpi_db_password'] ?? ''));
        if ($newPass !== '') {
            if (! $this->cipher()->isAvailable()) {
                return ServiceResult::fail('No se puede cifrar la contraseña: falta encryption.key en el entorno.');
            }
            $data['glpi_db_password'] = $this->cipher()->encrypt($newPass);
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración guardada.');
    }

    /** @return int[] */
    private function parseIdList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $part = trim($part);
            if ($part === '' || ! ctype_digit($part)) {
                continue;
            }
            $id = (int) $part;
            if ($id >= 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    /**
     * Accepts a posted array (checkboxes) or a comma string.
     *
     * @param mixed $raw
     * @return int[]
     */
    private function parseIdListFromInput(mixed $raw): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $v) {
                if (is_numeric($v) && (int) $v >= 0) {
                    $id = (int) $v;
                    $out[$id] = $id;
                }
            }
            return array_values($out);
        }
        return $this->parseIdList((string) $raw);
    }

    // ------------------------------------------------------------------
    // Fase 2: IA notifications
    // ------------------------------------------------------------------

    public function aiReuseServicedesk(): bool
    {
        return $this->model->get('ai_api_key_reuse_servicedesk', '1') === '1';
    }

    public function aiModel(): string
    {
        $v = trim($this->model->get('ai_model', 'claude-haiku-4-5'));
        return $v !== '' ? $v : 'claude-haiku-4-5';
    }

    public function aiMaxTokens(): int
    {
        return max(256, (int) $this->model->get('ai_max_tokens', '2048'));
    }

    /**
     * Decrypted Anthropic API key. Uses the module's own key when set; otherwise,
     * when reuse is on, falls back to ServiceDesk's key. '' when none available.
     */
    public function aiApiKey(): string
    {
        $own = $this->cipher()->decrypt($this->model->get('ai_api_key', ''));
        if ($own !== '') {
            return $own;
        }
        if ($this->aiReuseServicedesk()) {
            try {
                return (string) service('serviceDeskSettings')->aiApiKey();
            } catch (\Throwable) {
                return '';
            }
        }
        return '';
    }

    /** Whether IA drafting is usable: a key is resolvable and the cipher works. */
    public function aiReady(): bool
    {
        return $this->aiApiKey() !== '' && $this->cipher()->isAvailable();
    }

    public function notificationSenderName(): string
    {
        return trim($this->model->get('notification_sender_name', ''));
    }

    public function notificationSenderEmail(): string
    {
        return trim($this->model->get('notification_sender_email', ''));
    }

    /** @return string[] optional CC recipients for every notification. */
    public function notificationCc(): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', $this->model->get('notification_cc', '')) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($part)] = $part;
            }
        }
        return array_values($out);
    }

    /**
     * Persists the Fase 2 (IA notifications) settings form. The API key is stored
     * encrypted and only overwritten when a new value is submitted.
     */
    public function saveNotifications(array $input): ServiceResult
    {
        $senderEmail = trim((string) ($input['notification_sender_email'] ?? ''));
        if ($senderEmail !== '' && ! filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('El correo del remitente no es válido.');
        }

        $data = [
            'ai_api_key_reuse_servicedesk' => ! empty($input['ai_api_key_reuse_servicedesk']) ? '1' : '0',
            'ai_model'                     => trim((string) ($input['ai_model'] ?? 'claude-haiku-4-5')),
            'ai_max_tokens'                => (string) max(256, (int) ($input['ai_max_tokens'] ?? 2048)),
            'notification_sender_name'     => trim((string) ($input['notification_sender_name'] ?? '')),
            'notification_sender_email'    => $senderEmail,
            'notification_cc'              => implode(', ', $this->notificationCcFromRaw((string) ($input['notification_cc'] ?? ''))),
        ];

        $newKey = trim((string) ($input['ai_api_key'] ?? ''));
        if ($newKey !== '') {
            if (! $this->cipher()->isAvailable()) {
                return ServiceResult::fail('No se puede cifrar la API key: falta encryption.key en el entorno.');
            }
            $data['ai_api_key'] = $this->cipher()->encrypt($newKey);
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración de notificaciones guardada.');
    }

    /** @return string[] */
    private function notificationCcFromRaw(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($part)] = $part;
            }
        }
        return array_values($out);
    }

    private function cipher(): CredentialCipher
    {
        return $this->cipher ??= new CredentialCipher();
    }
}
