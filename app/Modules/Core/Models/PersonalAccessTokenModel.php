<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class PersonalAccessTokenModel extends Model
{
    protected $table         = 'personal_access_tokens';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'token_hash', 'name', 'last_used_at', 'expires_at'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    public function findValidToken(string $plainToken): ?array
    {
        $hash = hash('sha256', $plainToken);

        return $this->where('token_hash', $hash)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->first();
    }

    public function touchLastUsed(int $tokenId): void
    {
        $this->update($tokenId, ['last_used_at' => date('Y-m-d H:i:s')]);
    }

    public function createToken(int $userId, string $name, ?string $expiresAt = null): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $this->insert([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'name'       => $name,
            'expires_at' => $expiresAt,
        ]);

        return $plainToken;
    }
}
