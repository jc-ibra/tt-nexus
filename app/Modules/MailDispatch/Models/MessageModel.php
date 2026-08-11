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
        'cc_recipients',
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
     * Idempotency guard by RFC Message-ID. Catches the case where the same mail
     * arrives under a different backend id — e.g. a reply we sent over SMTP whose
     * forwarded Sent copy re-syncs from IMAP with a fresh UID.
     */
    public function existsByInternetMessageId(string $internetMessageId): bool
    {
        $id = trim($internetMessageId);
        if ($id === '') {
            return false;
        }
        return $this->where('internet_message_id', $id)->countAllResults() > 0;
    }

    /**
     * Detects a second delivery of a mail already stored.
     *
     * When several recipients of the same mail redirect into the shared mailbox,
     * every copy lands with its own Message-ID, so existsByInternetMessageId()
     * misses them and each copy would open its own conversation. The copies do
     * share sender, subject and the exact received instant, which is what this
     * matches on.
     *
     * Deliberately strict: the second, not the minute, because two unrelated
     * mails sharing sender AND subject AND the same second is not a case that
     * happens in practice. The body is NOT compared — verified copies of the
     * same mail differ in size (routing/rendering noise), so a body hash would
     * never match.
     */
    public function existsDuplicateDelivery(string $fromEmail, string $subject, ?string $receivedAt): bool
    {
        $fromEmail = trim($fromEmail);
        $subject   = trim($subject);
        $receivedAt = trim((string) $receivedAt);

        // Any missing piece makes the match unsafe: fall through and let the
        // message in rather than risk dropping a distinct mail.
        if ($fromEmail === '' || $subject === '' || $receivedAt === '') {
            return false;
        }

        return $this->where('from_email', $fromEmail)
            ->where('subject', $subject)
            ->where('received_at', $receivedAt)
            ->countAllResults() > 0;
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
