<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class ConversationModel extends Model
{
    protected $table         = 'maildispatch_conversations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'conversation_id',
        'mailbox_address',
        'subject',
        'requester_name',
        'requester_email',
        'status',
        'agent_id',
        'disposition_id',
        'glpi_folio',
        'close_comment',
        'message_count',
        'received_at',
        'assigned_at',
        'first_response_at',
        'last_activity_at',
        'closed_at',
    ];

    /** Find a conversation by its Graph conversationId (threading key). */
    public function findByGraphId(string $conversationId): ?array
    {
        return $this->where('conversation_id', $conversationId)->first();
    }

    /**
     * Inbox query. $filter ∈ {unassigned, mine, all, open, closed}. Returns the
     * conversation rows joined with the assigned agent's name and disposition.
     */
    public function forQueue(string $filter, ?int $userId, int $perPage = 25, string $q = ''): array
    {
        $b = $this->select(
            'maildispatch_conversations.*,'
            . ' core_users.name AS agent_name,'
            . ' maildispatch_dispositions.name AS disposition_name'
        )
            // Whether the thread has any attachment (for the list icon).
            ->select('(SELECT MAX(mm2.has_attachments) FROM maildispatch_messages mm2 WHERE mm2.conversation_id = maildispatch_conversations.id) AS has_attachments', false)
            ->join('core_users', 'core_users.id = maildispatch_conversations.agent_id', 'left')
            ->join('maildispatch_dispositions', 'maildispatch_dispositions.id = maildispatch_conversations.disposition_id', 'left');

        switch ($filter) {
            case 'unassigned':
                $b->where('maildispatch_conversations.agent_id', null)
                  ->where('maildispatch_conversations.status !=', 'cerrada');
                break;
            case 'mine':
                $b->where('maildispatch_conversations.agent_id', $userId)
                  ->where('maildispatch_conversations.status !=', 'cerrada');
                break;
            case 'closed':
                $b->where('maildispatch_conversations.status', 'cerrada');
                break;
            case 'all':
            default:
                // everything, open first
                break;
        }

        // Free-text search across subject, requester and GLPI folio. Grouped so
        // the OR set does not break the filter's AND conditions above.
        $q = trim($q);
        if ($q !== '') {
            $b->groupStart()
              ->like('maildispatch_conversations.subject', $q)
              ->orLike('maildispatch_conversations.requester_name', $q)
              ->orLike('maildispatch_conversations.requester_email', $q)
              ->orLike('maildispatch_conversations.glpi_folio', $q)
              ->groupEnd();
        }

        // Paginated: the pager is available afterwards via $model->pager. The
        // page number is read from the ?page= query param automatically.
        return $b->orderBy('maildispatch_conversations.last_activity_at', 'DESC')
                 ->paginate($perPage, 'default');
    }

    /** Conversation joined with agent + disposition names for the detail view. */
    public function findFull(int $id): ?array
    {
        return $this->select(
            'maildispatch_conversations.*,'
            . ' core_users.name AS agent_name,'
            . ' core_users.email AS agent_email,'
            . ' maildispatch_dispositions.name AS disposition_name'
        )
            ->join('core_users', 'core_users.id = maildispatch_conversations.agent_id', 'left')
            ->join('maildispatch_dispositions', 'maildispatch_dispositions.id = maildispatch_conversations.disposition_id', 'left')
            ->where('maildispatch_conversations.id', $id)
            ->first();
    }

    /** Count of currently unassigned, non-closed conversations (badge/metrics). */
    public function countUnassigned(): int
    {
        return $this->where('agent_id', null)->where('status !=', 'cerrada')->countAllResults();
    }

    /**
     * Per-tab counts for the inbox badges. Honors the same free-text search as
     * the list, so the numbers match what each tab would show.
     *
     * @return array{unassigned:int,mine:int,all:int,closed:int}
     */
    public function counts(?int $userId, string $q = ''): array
    {
        $q = trim($q);
        $search = function ($b) use ($q) {
            if ($q !== '') {
                $b->groupStart()
                  ->like('subject', $q)
                  ->orLike('requester_name', $q)
                  ->orLike('requester_email', $q)
                  ->orLike('glpi_folio', $q)
                  ->groupEnd();
            }
            return $b;
        };

        return [
            'unassigned' => $search($this->where('agent_id', null)->where('status !=', 'cerrada'))->countAllResults(),
            'mine'       => $search($this->where('agent_id', $userId)->where('status !=', 'cerrada'))->countAllResults(),
            'all'        => $search($this)->countAllResults(),
            'closed'     => $search($this->where('status', 'cerrada'))->countAllResults(),
        ];
    }
}
