<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\AppSettingsModel;

class AppSettingsService
{
    private AppSettingsModel $model;

    public function __construct(AppSettingsModel $model)
    {
        $this->model = $model;
    }

    // -----------------------------------------------------------------------
    // SMTP
    // -----------------------------------------------------------------------

    public function getSmtp(): array
    {
        $keys = ['smtp_host', 'smtp_port', 'smtp_crypto', 'smtp_user', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];
        $out  = [];

        foreach ($keys as $k) {
            $out[$k] = $this->model->get($k);
        }

        return $out;
    }

    public function saveSmtp(array $data): ServiceResult
    {
        $allowed = ['smtp_host', 'smtp_port', 'smtp_crypto', 'smtp_user', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];
        $toSave  = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $toSave[$key] = trim((string) $data[$key]);
            }
        }

        // Never overwrite password if left blank (preserve existing)
        if (isset($toSave['smtp_password']) && $toSave['smtp_password'] === '') {
            unset($toSave['smtp_password']);
        }

        try {
            $this->model->setMany($toSave);
            log_message('info', '[AppSettings] SMTP settings updated by user_id=' . session()->get('user_id'));
            return ServiceResult::ok(null, 'Configuración SMTP guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', '[AppSettings] Failed to save SMTP settings: ' . $e->getMessage());
            return ServiceResult::fail($e->getMessage());
        }
    }

    public function isSmtpConfigured(): bool
    {
        return $this->model->get('smtp_host') !== '';
    }

    // -----------------------------------------------------------------------
    // Generic getter for external use
    // -----------------------------------------------------------------------

    public function get(string $key, string $default = ''): string
    {
        return $this->model->get($key, $default);
    }
}
