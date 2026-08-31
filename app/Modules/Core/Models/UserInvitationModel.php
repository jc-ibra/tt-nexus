<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use CodeIgniter\Model;

class UserInvitationModel extends Model
{
    protected $table         = 'core_user_invitations';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id', 'email', 'token_hash', 'invited_by',
        'expires_at', 'accepted_at', 'revoked_at', 'sent_count',
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /**
     * Issues a fresh single-use token for the user. Any previous pending
     * invitation is revoked first, so only one link is ever live: resending
     * invalidates the old email.
     *
     * @return string the plain token — only ever exists here and in the email
     */
    public function issue(int $userId, string $email, ?int $invitedBy, int $ttlHours): string
    {
        $previous = $this->pendingFor($userId);
        $this->revokeFor($userId);

        $plain = bin2hex(random_bytes(32));

        $this->insert([
            'user_id'    => $userId,
            'email'      => $email,
            'token_hash' => hash('sha256', $plain),
            'invited_by' => $invitedBy,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours")),
            'sent_count' => $previous === null ? 1 : (int) $previous['sent_count'] + 1,
        ]);

        return $plain;
    }

    public function findValidByToken(string $plain): ?array
    {
        return $this->where('token_hash', hash('sha256', $plain))
            ->where('accepted_at', null)
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * The live invitation for a user, if any: not accepted, not revoked and
     * not expired.
     */
    public function pendingFor(int $userId): ?array
    {
        return $this->where('user_id', $userId)
            ->where('accepted_at', null)
            ->where('revoked_at', null)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function markAccepted(int $id): void
    {
        $this->update($id, ['accepted_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeFor(int $userId): void
    {
        $this->where('user_id', $userId)
            ->where('accepted_at', null)
            ->where('revoked_at', null)
            ->set(['revoked_at' => date('Y-m-d H:i:s')])
            ->update();
    }

    /**
     * Pending invitations for a set of users, keyed by user id. Used by the
     * users list so the badge does not cost one query per row.
     *
     * @param int[] $userIds
     * @return array<int, array>
     */
    public function pendingForUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->whereIn('user_id', $userIds)
            ->where('accepted_at', null)
            ->where('revoked_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['user_id']] = $row;
        }

        return $out;
    }
}
