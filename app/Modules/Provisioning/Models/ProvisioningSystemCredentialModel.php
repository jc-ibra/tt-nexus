<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Model;

class ProvisioningSystemCredentialModel extends Model
{
    protected $table         = 'provisioning_system_credentials';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'system_id', 'credential_name', 'value_encrypted', 'created_at', 'rotated_at',
    ];

    public function findFor(int $systemId, string $credentialName): ?array
    {
        return $this->where('system_id', $systemId)
            ->where('credential_name', $credentialName)
            ->first();
    }

    public function listFor(int $systemId): array
    {
        return $this->where('system_id', $systemId)->findAll();
    }

    public function upsert(int $systemId, string $credentialName, string $encryptedValue): void
    {
        $existing = $this->findFor($systemId, $credentialName);
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            $this->update($existing['id'], [
                'value_encrypted' => $encryptedValue,
                'rotated_at'      => $now,
            ]);
        } else {
            $this->insert([
                'system_id'       => $systemId,
                'credential_name' => $credentialName,
                'value_encrypted' => $encryptedValue,
                'created_at'      => $now,
                'rotated_at'      => $now,
            ]);
        }
    }

    public function deleteFor(int $systemId, string $credentialName): void
    {
        $this->where('system_id', $systemId)
            ->where('credential_name', $credentialName)
            ->delete();
    }
}
