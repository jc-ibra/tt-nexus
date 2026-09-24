<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class AttachmentModel extends Model
{
    protected $table         = 'maildispatch_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // attachments are immutable; only created_at

    protected $allowedFields = [
        'message_id',
        'conversation_id',
        'filename',
        'mime_type',
        'size_bytes',
        'storage_path',
        'content_id',
        'is_inline',
        'direction',
    ];

    /** All attachments of a message, oldest first. */
    public function forMessage(int $messageId): array
    {
        return $this->where('message_id', $messageId)->orderBy('id', 'ASC')->findAll();
    }

    /**
     * Attachments for several messages in one query, grouped by message_id —
     * for rendering a full thread, where calling forMessage() per message in a
     * loop turns into one query per message.
     *
     * @param  int[] $messageIds
     * @return array<int,array<int,array<string,mixed>>> message_id => attachments, oldest first
     */
    public function forMessages(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $rows = $this->whereIn('message_id', $messageIds)->orderBy('id', 'ASC')->findAll();

        $byMessage = [];
        foreach ($rows as $row) {
            $byMessage[(int) $row['message_id']][] = $row;
        }

        return $byMessage;
    }

    /** Only the inline (cid-referenced) attachments of a message. */
    public function inlineForMessage(int $messageId): array
    {
        return $this->where('message_id', $messageId)
            ->where('is_inline', 1)
            ->where('content_id IS NOT NULL', null, false)
            ->findAll();
    }
}
