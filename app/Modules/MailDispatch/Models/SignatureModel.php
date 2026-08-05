<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * Per-agent email signature (keyed by user id, one row per user). The signature
 * is appended to replies sent from Nexus.
 */
class SignatureModel extends Model
{
    protected $table         = 'maildispatch_signatures';
    protected $primaryKey    = 'user_id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'body_html'];

    /** The user's signature HTML, or '' when none is configured. */
    public function forUser(int $userId): string
    {
        $row = $this->find($userId);
        return (string) ($row['body_html'] ?? '');
    }

    /** Upserts the signature for a user. */
    public function saveFor(int $userId, string $html): void
    {
        if ($this->find($userId) !== null) {
            $this->update($userId, ['body_html' => $html]);
        } else {
            $this->insert(['user_id' => $userId, 'body_html' => $html]);
        }
    }
}
