<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\MailDispatchSettingsModel;

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

    public function isSyncEnabled(): bool { return $this->model->get('sync_enabled', '0') === '1'; }
    public function isSendEnabled(): bool { return $this->model->get('send_from_nexus_enabled', '0') === '1'; }

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

    /** True only when tenant, client id, secret and mailbox are all present. */
    public function isConfigured(): bool
    {
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

        if ($mailbox !== '' && ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('La dirección del buzón no es un correo válido.');
        }

        $data = [
            'graph_tenant_id'             => $tenant,
            'graph_client_id'             => $clientId,
            'mailbox_address'             => $mailbox,
            'sync_enabled'                => isset($post['sync_enabled']) ? '1' : '0',
            'sync_page_size'              => (string) max(1, (int) ($post['sync_page_size'] ?? 50)),
            'sla_unassigned_minutes'      => (string) max(0, (int) ($post['sla_unassigned_minutes'] ?? 30)),
            'sla_first_response_minutes'  => (string) max(0, (int) ($post['sla_first_response_minutes'] ?? 120)),
            'send_from_nexus_enabled'     => isset($post['send_from_nexus_enabled']) ? '1' : '0',
        ];

        // Only overwrite the secret when a fresh value is provided.
        $secret = (string) ($post['graph_client_secret'] ?? '');
        if ($secret !== '' && $secret !== self::SECRET_MASK) {
            $data['graph_client_secret'] = $secret;
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración de Despacho guardada.');
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
}
