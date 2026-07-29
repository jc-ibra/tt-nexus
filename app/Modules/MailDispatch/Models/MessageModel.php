<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table         = 'maildispatch_messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // messages are immutable; only created_at

    protected $allowedFields = [
        'conversation_id',
        'graph_id',
        'internet_message_id',
        'in_reply_to',
        'references_header',
        'direction',
        'from_name',
        'from_email',
        'to_recipients',
        'subject',
        'body_preview',
        'body',
        'body_is_html',
        'has_attachments',
        'attachment_names',
        'received_at',
    ];

    /** Idempotency guard: has this Graph message already been ingested? */
    public function existsByGraphId(string $graphId): bool
    {
        return $this->where('graph_id', $graphId)->countAllResults() > 0;
    }

    /**
     * Threading fallback: resolve the conversation a message belongs to when
     * Graph's conversationId is missing/broken, by matching In-Reply-To /
     * References against an already-stored internet_message_id.
     */
    public function conversationIdForReference(array $messageIds): ?int
    {
        $messageIds = array_values(array_filter(array_map('trim', $messageIds)));
        if ($messageIds === []) {
            return null;
        }
        $row = $this->select('conversation_id')
            ->whereIn('internet_message_id', $messageIds)
            ->orderBy('id', 'DESC')
            ->first();

        return $row ? (int) $row['conversation_id'] : null;
    }

    /** Full thread for a conversation, oldest first. */
    public function forConversation(int $conversationId): array
    {
        return $this->where('conversation_id', $conversationId)
            ->orderBy('received_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
