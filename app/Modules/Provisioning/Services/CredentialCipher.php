<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use CodeIgniter\Encryption\EncrypterInterface;
use Config\Services;

/**
 * Wraps CI4's Encryption service so credentials are never stored in plain text.
 * The master key lives in .env as `encryption.key` (e.g. hex2bin:...) — never in the repo.
 */
class CredentialCipher
{
    private ?EncrypterInterface $encrypter = null;

    public function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        return base64_encode($this->encrypter()->encrypt($plain));
    }

    public function decrypt(?string $encoded): string
    {
        if ($encoded === null || $encoded === '') {
            return '';
        }
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            return '';
        }
        try {
            return $this->encrypter()->decrypt($raw);
        } catch (\Throwable $e) {
            log_message('error', '[CredentialCipher] decrypt failed: ' . $e->getMessage());
            return '';
        }
    }

    public function isAvailable(): bool
    {
        try {
            $this->encrypter();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function encrypter(): EncrypterInterface
    {
        if ($this->encrypter === null) {
            $this->encrypter = Services::encrypter();
        }
        return $this->encrypter;
    }
}
