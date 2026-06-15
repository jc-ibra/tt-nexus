<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Models\ProvisioningSystemCredentialModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;

/**
 * Admin facade for the provisioning_systems catalog: edit URL, options, notes,
 * upsert encrypted credentials, toggle active flag.
 *
 * Plain credentials are NEVER read back — the form shows a "(definida)" hint
 * when a credential exists; an empty field on save means "leave as is".
 */
class SystemAdminService
{
    public function __construct(
        private ProvisioningSystemModel $systems,
        private ProvisioningSystemCredentialModel $credentials,
        private CredentialCipher $cipher,
    ) {}

    public function list(): array
    {
        return $this->systems->listAll();
    }

    public function find(int $systemId): ?array
    {
        $system = $this->systems->find($systemId);
        if (! $system) {
            return null;
        }
        $system['credential_names'] = array_map(
            fn($c) => $c['credential_name'],
            $this->credentials->listFor($systemId),
        );
        $system['options_array'] = $this->systems->getOptions($system);
        return $system;
    }

    public function update(int $systemId, array $data, array $credentials): ServiceResult
    {
        $system = $this->systems->find($systemId);
        if (! $system) {
            return ServiceResult::fail('Sistema no encontrado.');
        }

        if (! $this->cipher->isAvailable()) {
            return ServiceResult::fail('La clave de cifrado (encryption.key) no está configurada en .env. No es seguro guardar credenciales.');
        }

        $update = [];
        foreach (['base_url', 'description', 'notes', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = trim((string) $data[$field]);
            }
        }

        if (isset($data['is_active'])) {
            $update['is_active'] = ((int) $data['is_active']) === 1 ? 1 : 0;
        }

        // Merge options: keep existing keys, overwrite the ones the form sent.
        if (! empty($data['options']) && is_array($data['options'])) {
            $current = $this->systems->getOptions($system);
            $merged  = array_merge($current, $data['options']);
            $update['options'] = json_encode($merged, JSON_UNESCAPED_UNICODE);
        }

        if (! empty($update)) {
            $this->systems->update($systemId, $update);
        }

        // Upsert credentials. An empty value means "do not change".
        foreach ($credentials as $name => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $this->credentials->upsert($systemId, (string) $name, $this->cipher->encrypt($value));
        }

        log_message('info', "[Provisioning] System #{$systemId} updated by user_id=" . session()->get('user_id'));

        return ServiceResult::ok(null, 'Sistema actualizado correctamente.');
    }

    public function toggleActive(int $systemId): ServiceResult
    {
        $system = $this->systems->find($systemId);
        if (! $system) {
            return ServiceResult::fail('Sistema no encontrado.');
        }
        $newState = ((int) $system['is_active']) === 1 ? 0 : 1;
        $this->systems->update($systemId, ['is_active' => $newState]);
        return ServiceResult::ok(['is_active' => $newState], $newState === 1 ? 'Sistema activado.' : 'Sistema desactivado.');
    }

    /**
     * Returns the list of credential names each system expects.
     * Used by the edit form to render the right inputs.
     */
    public function expectedCredentials(string $systemKey): array
    {
        return match ($systemKey) {
            'glpi'     => [
                'app_token'    => ['label' => 'App-Token (Application token)',     'required' => true],
                'user_token'   => ['label' => 'User token (recomendado)',          'required' => false],
                'api_username' => ['label' => 'Usuario API (alternativa)',          'required' => false],
                'api_password' => ['label' => 'Contraseña API (alternativa)',       'required' => false, 'type' => 'password'],
            ],
            'mailcow'  => [
                'api_key'      => ['label' => 'API Key (sólo si NO reutilizas Buzones)', 'required' => false, 'type' => 'password'],
            ],
            'intranet' => [
                'api_key'      => ['label' => 'API Key (Bearer)', 'required' => true, 'type' => 'password'],
            ],
            default => [],
        };
    }
}
