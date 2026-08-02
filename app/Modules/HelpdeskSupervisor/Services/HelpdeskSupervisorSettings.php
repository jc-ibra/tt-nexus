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

    private function cipher(): CredentialCipher
    {
        return $this->cipher ??= new CredentialCipher();
    }
}
