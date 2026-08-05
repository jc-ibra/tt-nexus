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

    /** Only the inline (cid-referenced) attachments of a message. */
    public function inlineForMessage(int $messageId): array
    {
        return $this->where('message_id', $messageId)
            ->where('is_inline', 1)
            ->where('content_id IS NOT NULL', null, false)
            ->findAll();
    }
}
