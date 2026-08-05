<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class MessageRefModel extends Model
{
    protected $table         = 'maildispatch_message_refs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = ['message_id', 'ref_id'];

    /** Stores a message's unique thread tokens (idempotent). */
    public function storeTokens(int $messageId, array $tokens): void
    {
        $tokens = array_values(array_unique(array_filter(array_map('trim', $tokens))));
        if ($tokens === []) {
            return;
        }
        $rows = array_map(static fn (string $t) => ['message_id' => $messageId, 'ref_id' => $t], $tokens);
        $this->db->table($this->table)->ignore(true)->insertBatch($rows);
    }

    /**
     * Finds the conversation a new message belongs to by token overlap: any of
     * its tokens already stored against an existing message. Returns the oldest
     * matching conversation id (stable target when duplicates still exist).
     */
    public function conversationByTokens(array $tokens): ?int
    {
        $tokens = array_values(array_unique(array_filter(array_map('trim', $tokens))));
        if ($tokens === []) {
            return null;
        }
        $row = $this->db->table($this->table . ' r')
            ->select('m.conversation_id')
            ->join('maildispatch_messages m', 'm.id = r.message_id')
            ->whereIn('r.ref_id', $tokens)
            ->orderBy('m.id', 'ASC')
            ->get(1)->getRowArray();

        return $row ? (int) $row['conversation_id'] : null;
    }
}
