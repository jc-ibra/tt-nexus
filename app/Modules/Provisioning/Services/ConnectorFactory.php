<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Provisioning\Connectors\GlpiConnector;
use App\Modules\Provisioning\Connectors\IntranetConnector;
use App\Modules\Provisioning\Connectors\MailcowConnector;
use App\Modules\Provisioning\Connectors\SystemConnector;
use App\Modules\Provisioning\Models\ProvisioningSystemCredentialModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use RuntimeException;

/**
 * Builds a SystemConnector for a given provisioning_systems row, hydrating it
 * with its decrypted credentials and parsed options.
 *
 * Adding a new connector? Register its key here and provide an implementation
 * of SystemConnector. The orchestrator does not change.
 */
class ConnectorFactory
{
    public function __construct(
        private ProvisioningSystemModel $systemModel,
        private ProvisioningSystemCredentialModel $credentialModel,
        private CredentialCipher $cipher,
    ) {}

    public function buildById(int $systemId): SystemConnector
    {
        $system = $this->systemModel->find($systemId);
        if (! $system) {
            throw new RuntimeException("Sistema #{$systemId} no encontrado.");
        }
        return $this->build($system);
    }

    public function buildByKey(string $key): SystemConnector
    {
        $system = $this->systemModel->findByKey($key);
        if (! $system) {
            throw new RuntimeException("Sistema '{$key}' no encontrado.");
        }
        return $this->build($system);
    }

    public function build(array $system): SystemConnector
    {
        $credentials = $this->decryptCredentials((int) $system['id']);
        $options     = $this->systemModel->getOptions($system);

        return match ($system['key']) {
            'glpi'     => new GlpiConnector($system, $credentials, $options),
            'mailcow'  => new MailcowConnector($system, $credentials, $options),
            'intranet' => new IntranetConnector($system, $credentials, $options),
            default    => throw new RuntimeException("No hay conector implementado para '{$system['key']}'."),
        };
    }

    private function decryptCredentials(int $systemId): array
    {
        $rows = $this->credentialModel->listFor($systemId);
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['credential_name']] = $this->cipher->decrypt($row['value_encrypted']);
        }
        return $out;
    }
}
