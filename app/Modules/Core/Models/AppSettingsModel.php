<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Manages the app_settings key-value table.
 *
 * ENCRYPTED_KEYS are stored as AES-256-CBC ciphertext (base64-encoded).
 * The encryption key comes from env('APP_SETTINGS_ENCRYPTION_KEY') — a 64-char
 * hex string (32 bytes). Never store these values in plaintext.
 */
class AppSettingsModel
{
    private const ENCRYPTED_KEYS = ['smtp_user', 'smtp_password'];

    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->table('core_app_settings')->where('key', $key)->get()->getRow();
        $val = $row ? (string) $row->value : $default;

        if ($val !== '' && in_array($key, self::ENCRYPTED_KEYS, true)) {
            return $this->decryptValue($val);
        }

        return $val;
    }

    public function getAll(): array
    {
        $rows = $this->db->table('core_app_settings')->get()->getResultArray();
        $out  = [];

        foreach ($rows as $row) {
            $k   = $row['key'];
            $val = (string) $row['value'];

            if ($val !== '' && in_array($k, self::ENCRYPTED_KEYS, true)) {
                $val = $this->decryptValue($val);
            }

            $out[$k] = $val;
        }

        return $out;
    }

    public function set(string $key, string $value): void
    {
        if ($value !== '' && in_array($key, self::ENCRYPTED_KEYS, true)) {
            $value = $this->encryptValue($value);
        }

        $now      = date('Y-m-d H:i:s');
        $existing = $this->db->table('core_app_settings')->where('key', $key)->countAllResults();

        if ($existing) {
            $this->db->table('core_app_settings')->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table('core_app_settings')->insert([
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

    // -----------------------------------------------------------------------
    // Encryption helpers (AES-256-CBC, key from .env)
    // -----------------------------------------------------------------------

    private function encryptionKey(): string
    {
        $hex = (string) env('APP_SETTINGS_ENCRYPTION_KEY', '');

        if (strlen($hex) !== 64 || ! ctype_xdigit($hex)) {
            throw new \RuntimeException(
                'APP_SETTINGS_ENCRYPTION_KEY must be a 64-character hex string in .env. ' .
                'Generate one with: python3 -c "import secrets; print(secrets.token_hex(32))"'
            );
        }

        return hex2bin($hex);
    }

    private function encryptValue(string $plaintext): string
    {
        $key    = $this->encryptionKey();
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            throw new \RuntimeException('AppSettingsModel: openssl_encrypt failed.');
        }

        return base64_encode($iv . $cipher);
    }

    private function decryptValue(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);

        if ($decoded === false || strlen($decoded) <= 16) {
            log_message('warning', '[AppSettings] Stored value is not valid ciphertext — returning empty. Re-save the setting.');
            return '';
        }

        $key       = $this->encryptionKey();
        $iv        = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        $plain     = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($plain === false) {
            log_message('error', '[AppSettings] Decryption failed — returning empty. Check APP_SETTINGS_ENCRYPTION_KEY in .env.');
            return '';
        }

        return $plain;
    }
}
