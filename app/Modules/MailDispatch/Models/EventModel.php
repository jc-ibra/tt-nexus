<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class EventModel extends Model
{
    protected $table         = 'maildispatch_events';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // append-only audit log

    protected $allowedFields = [
        'conversation_id',
        'user_id',
        'type',
        'from_value',
        'to_value',
        'note',
    ];

    /** Append an audit entry. $userId null = system (auto-transition on sync). */
    public function log(int $conversationId, string $type, ?int $userId = null, ?string $from = null, ?string $to = null, ?string $note = null): void
    {
        $this->insert([
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'type'            => $type,
            'from_value'      => $from,
            'to_value'        => $to,
            'note'            => $note,
        ]);
    }

    /**
     * Cross-conversation activity feed for the team board: who took, reassigned,
     * closed or reopened what, newest first. Internal notes are excluded — they
     * are written for the thread, not for a team-wide ticker.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentActivity(int $limit = 25): array
    {
        return $this->select(
            'maildispatch_events.*,'
            . ' core_users.name AS user_name,'
            . ' maildispatch_conversations.subject AS subject,'
            . ' maildispatch_conversations.requester_name AS requester_name'
        )
            ->join('core_users', 'core_users.id = maildispatch_events.user_id', 'left')
            ->join('maildispatch_conversations', 'maildispatch_conversations.id = maildispatch_events.conversation_id', 'inner')
            ->whereIn('maildispatch_events.type', ['assign', 'reassign', 'unassign', 'close', 'reopen'])
            ->orderBy('maildispatch_events.id', 'DESC')
            ->findAll($limit);
    }

    /** Timeline (bitácora + notes) for a conversation, newest first. */
    public function forConversation(int $conversationId): array
    {
        return $this->select('maildispatch_events.*, core_users.name AS user_name')
            ->join('core_users', 'core_users.id = maildispatch_events.user_id', 'left')
            ->where('conversation_id', $conversationId)
            ->orderBy('maildispatch_events.id', 'DESC')
            ->findAll();
    }
}
