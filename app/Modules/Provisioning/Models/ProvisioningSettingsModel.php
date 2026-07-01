<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use App\Modules\Provisioning\Services\CredentialCipher;
use CodeIgniter\Database\BaseConnection;

/**
 * Manages the provisioning_settings key-value table.
 *
 * ENCRYPTED_KEYS are stored as ciphertext through the shared CredentialCipher
 * (AES via CI4's encrypter, master key = encryption.key in .env). Everything
 * else (host, port, name, user) is stored in plaintext since it is not secret.
 */
class ProvisioningSettingsModel
{
    private const ENCRYPTED_KEYS = ['glpi_db_password'];

    private BaseConnection $db;
    private CredentialCipher $cipher;

    public function __construct(?CredentialCipher $cipher = null)
    {
        $this->db     = \Config\Database::connect();
        $this->cipher = $cipher ?? new CredentialCipher();
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->table('provisioning_settings')->where('key', $key)->get()->getRow();
        $val = $row ? (string) $row->value : $default;

        if ($val !== '' && in_array($key, self::ENCRYPTED_KEYS, true)) {
            return $this->cipher->decrypt($val);
        }

        return $val;
    }

    public function getAll(): array
    {
        $rows = $this->db->table('provisioning_settings')->get()->getResultArray();
        $out  = [];

        foreach ($rows as $row) {
            $k   = $row['key'];
            $val = (string) $row['value'];

            if ($val !== '' && in_array($k, self::ENCRYPTED_KEYS, true)) {
                $val = $this->cipher->decrypt($val);
            }

            $out[$k] = $val;
        }

        return $out;
    }

    public function set(string $key, string $value): void
    {
        if ($value !== '' && in_array($key, self::ENCRYPTED_KEYS, true)) {
            $value = $this->cipher->encrypt($value);
        }

        $now      = date('Y-m-d H:i:s');
        $existing = $this->db->table('provisioning_settings')->where('key', $key)->countAllResults();

        if ($existing) {
            $this->db->table('provisioning_settings')->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table('provisioning_settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'updated_at' => $now,
            ]);
        }
    }

    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, (string) $value);
        }
    }
}
