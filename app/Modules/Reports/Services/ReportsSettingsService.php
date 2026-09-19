<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Models\ReportSettingsModel;

/**
 * Typed accessors over reports_settings, mirroring the
 * HelpdeskSupervisorSettings / MailDispatchSettings pattern.
 */
class ReportsSettingsService
{
    public function __construct(private ReportSettingsModel $model) {}

    /**
     * Logical key => {container_id, field}. Each key can live in a different
     * plugin Additional Fields container (e.g. "ids" lives in its own
     * container, separate from the client-data one).
     *
     * @return array<string,array{container_id:int,field:string}>
     */
    public function glpiFieldBindings(): array
    {
        $decoded = json_decode($this->model->get('glpi_field_bindings', '{}'), true);
        if (! is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $key => $binding) {
            if (! is_array($binding) || empty($binding['field'])) {
                continue;
            }
            $out[$key] = ['container_id' => (int) ($binding['container_id'] ?? 0), 'field' => (string) $binding['field']];
        }
        return $out;
    }

    /** @return array{container_id:int,field:string}|null */
    public function glpiFieldBinding(string $logicalKey): ?array
    {
        return $this->glpiFieldBindings()[$logicalKey] ?? null;
    }

    public function slaHours(): int
    {
        return max(1, (int) $this->model->get('sla_hours', '24'));
    }

    public function trendMonths(): int
    {
        return max(1, (int) $this->model->get('trend_months', '12'));
    }

    public function aiEnabled(): bool
    {
        return $this->model->get('ai_enabled', '0') === '1';
    }

    public function aiModel(): string
    {
        return $this->model->get('ai_model', 'claude-sonnet-5');
    }

    public function aiReuseHelpdeskSupervisor(): bool
    {
        return $this->model->get('ai_reuse_helpdesk_supervisor', '1') === '1';
    }

    /** Resolves the effective API key: own override, else HelpdeskSupervisor's (which may itself reuse ServiceDesk's). */
    public function aiApiKey(): string
    {
        $own = $this->model->get('ai_api_key', '');
        if ($own !== '') {
            return $own;
        }
        if ($this->aiReuseHelpdeskSupervisor()) {
            try {
                return (string) service('helpdeskSupervisorSettings')->aiApiKey();
            } catch (\Throwable) {
                return '';
            }
        }
        return '';
    }

    public function aiReady(): bool
    {
        return $this->aiEnabled() && $this->aiApiKey() !== '';
    }

    /** @return string[] */
    public function emailRecipients(): array
    {
        $raw = $this->model->get('email_recipients', '');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function emailSenderName(): string
    {
        return $this->model->get('email_sender_name', 'Reportes tt-nexus');
    }

    public function save(array $data): void
    {
        $this->model->setMany($data);
    }

    public function getAllForDisplay(): array
    {
        $all = $this->model->getAll();
        // Never echo the decrypted key back to a form.
        $all['ai_api_key'] = $all['ai_api_key'] !== '' ? '••••••••' : '';
        return $all;
    }
}
