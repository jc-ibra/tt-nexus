<?php

declare(strict_types=1);

namespace App\Modules\Employees\Commands;

use App\Modules\Core\Models\PersonalAccessTokenModel;
use App\Modules\Provisioning\Models\ProvisioningSystemCredentialModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-shot local wiring for HR documents (intranet URL/key + API token).
 * LOCAL ONLY — do not run against production.
 */
class HrLocalSetup extends BaseCommand
{
    protected $group       = 'Employees';
    protected $name        = 'employees:hr-local-setup';
    protected $description = 'Wire local intranet connector + mint API token for HR docs callback';

    public function run(array $params)
    {
        $apiKey = $params[0] ?? 'a3f8c2e1b94d7056f2a1c3e8d4b7a9f1e2c5d8b3a6f9c2e5d8b1a4f7c0e3d6';
        $base   = $params[1] ?? 'http://host.docker.internal:8090';

        $systems = model(ProvisioningSystemModel::class);
        $system  = $systems->findByKey('intranet');
        if (! $system) {
            CLI::error('Sistema intranet no encontrado');
            return;
        }

        $systems->update((int) $system['id'], [
            'base_url'  => $base,
            'is_active' => 1,
        ]);

        model(ProvisioningSystemCredentialModel::class)->upsert(
            (int) $system['id'],
            'api_key',
            service('credentialCipher')->encrypt($apiKey)
        );

        $token = (new PersonalAccessTokenModel())->createToken(1, 'hr-docs-local');

        CLI::write('intranet.base_url=' . $base);
        CLI::write('intranet.api_key updated');
        CLI::write('PAT=' . $token);
    }
}
