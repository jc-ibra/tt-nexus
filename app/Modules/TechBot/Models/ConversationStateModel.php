<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Models;

use CodeIgniter\Model;

/**
 * At most one active conversation state per chat (unique telegram_chat_id).
 * The `context` column carries the JSON scratchpad the flows accumulate.
 */
class ConversationStateModel extends Model
{
    protected $table         = 'techbot_conversation_states';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'telegram_chat_id',
        'state',
        'context',
        'current_ticket_id',
        'expires_at',
    ];

    /** Inactivity timeout, in minutes, before a flow resets to idle. */
    public const TIMEOUT_MINUTES = 30;

    public function findByChatId(int $chatId): ?array
    {
        return $this->where('telegram_chat_id', $chatId)->first();
    }

    /**
     * Loads a chat's state, decoding the JSON context into an array. Returns a
     * synthetic idle state (not persisted) when none exists or it has expired.
     *
     * @return array{state:string,context:array,current_ticket_id:?int,expired:bool}
     */
    public function loadState(int $chatId): array
    {
        $row = $this->findByChatId($chatId);
        if ($row === null) {
            return ['state' => 'idle', 'context' => [], 'current_ticket_id' => null, 'expired' => false];
        }

        $expired = false;
        if (! empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            $expired = true;
        }

        $context = [];
        if (! empty($row['context'])) {
            $decoded = json_decode((string) $row['context'], true);
            $context = is_array($decoded) ? $decoded : [];
        }

        return [
            'state'             => $expired ? 'idle' : (string) $row['state'],
            'context'           => $expired ? [] : $context,
            'current_ticket_id' => $row['current_ticket_id'] !== null ? (int) $row['current_ticket_id'] : null,
            'expired'           => $expired,
        ];
    }

    /**
     * Persists (upsert) the chat state and refreshes the inactivity timeout.
     */
    public function saveState(int $chatId, string $state, array $context = [], ?int $currentTicketId = null): void
    {
        $data = [
            'telegram_chat_id'  => $chatId,
            'state'             => $state,
            'context'           => json_encode($context, JSON_UNESCAPED_UNICODE),
            'current_ticket_id' => $currentTicketId,
            'expires_at'        => date('Y-m-d H:i:s', time() + self::TIMEOUT_MINUTES * 60),
        ];

        $existing = $this->findByChatId($chatId);
        if ($existing) {
            $this->update((int) $existing['id'], $data);
        } else {
            $this->insert($data);
        }
    }

    /** Resets a chat back to idle (clears context and ticket). */
    public function reset(int $chatId): void
    {
        $this->saveState($chatId, 'idle', [], null);
    }
}
