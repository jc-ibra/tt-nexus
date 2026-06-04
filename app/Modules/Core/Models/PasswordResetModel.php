<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table         = 'password_resets';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['email', 'token_hash', 'expires_at'];
    protected $useTimestamps  = true;
    protected $updatedField  = '';
    protected $returnType    = 'array';

    public function createToken(string $email): string
    {
        $plain = bin2hex(random_bytes(32));

        $this->where('email', $email)->delete();

        $this->insert([
            'email'      => $email,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        return $plain;
    }

    public function findValidByToken(string $plain): ?array
    {
        return $this->where('token_hash', hash('sha256', $plain))
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    public function deleteByEmail(string $email): void
    {
        $this->where('email', $email)->delete();
    }
}
