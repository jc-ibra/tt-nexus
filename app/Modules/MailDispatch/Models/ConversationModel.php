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
        'outbound_only',
        'disposition_id',
        'auto_rule_id',
        'verified_by',
        'verified_at',
        'autogen_rule_id',
        'auto_ticket_id',
        'autogen_state',
        'autogen_payload',
        'autogen_attempts',
        'autogen_error',
        'autogen_reply_sent',
        'autogen_next_attempt_at',
        'glpi_folio',
        'close_comment',
        'message_count',
        'received_at',
        'assigned_at',
        'first_response_at',
        'last_activity_at',
        'closed_at',
    ];

    /**
     * Conversations whose message bodies match the search terms. Memoized inside
     * MessageModel, so the list query and the tab badges share one lookup.
     *
     * @return array<int,int>
     */
    private function bodyMatchIds(string $q): array
    {
        return (new MessageModel())->conversationIdsMatching($q);
    }

    /** Find a conversation by its Graph conversationId (threading key). */
    public function findByGraphId(string $conversationId): ?array
    {
        return $this->where('conversation_id', $conversationId)->first();
    }

    /**
     * Inbox query. $filter ∈ {unassigned, mine, all, open, closed}. Returns the
     * conversation rows joined with the assigned agent's name and disposition.
     */
    public function forQueue(string $filter, ?int $userId, int $perPage = 25, string $q = '', ?int $agentId = null): array
    {
        $b = $this->select(
            'maildispatch_conversations.*,'
            . ' core_users.name AS agent_name,'
            . ' vby.name AS verified_name,'
            . ' maildispatch_dispositions.name AS disposition_name'
        )
            // Whether the thread has any attachment (for the list icon).
            ->select('(SELECT MAX(mm2.has_attachments) FROM maildispatch_messages mm2 WHERE mm2.conversation_id = maildispatch_conversations.id) AS has_attachments', false)
            ->join('core_users', 'core_users.id = maildispatch_conversations.agent_id', 'left')
            ->join('core_users vby', 'vby.id = maildispatch_conversations.verified_by', 'left')
            ->join('maildispatch_dispositions', 'maildispatch_dispositions.id = maildispatch_conversations.disposition_id', 'left');

        switch ($filter) {
            case 'unassigned':
                $b->where('maildispatch_conversations.agent_id', null)
                  ->where('maildispatch_conversations.status !=', 'cerrada')
                  ->where('maildispatch_conversations.status !=', 'autoarchivo')
                  ->where('maildispatch_conversations.status !=', 'autogenerado')
                  // Threads the help desk itself started and nobody answered are
                  // not work waiting to be picked up. They stay visible in
                  // "Todas" and return here the moment someone replies.
                  ->where('maildispatch_conversations.outbound_only', 0);
                break;
            case 'mine':
                $b->where('maildispatch_conversations.agent_id', $userId)
                  ->where('maildispatch_conversations.status !=', 'cerrada')
                  ->where('maildispatch_conversations.status !=', 'autoarchivo')
                  ->where('maildispatch_conversations.status !=', 'autogenerado');
                break;
            case 'autoarchivo':
                // Auto-triaged bucket: pending (unverified) first.
                $b->where('maildispatch_conversations.status', 'autoarchivo')
                  ->orderBy('maildispatch_conversations.verified_at IS NULL', 'DESC', false);
                break;
            case 'autogenerado':
                // Auto-generated tickets bucket: actionable (unverified) first.
                $b->where('maildispatch_conversations.status', 'autogenerado')
                  ->orderBy('maildispatch_conversations.verified_at IS NULL', 'DESC', false);
                break;
            case 'closed':
                $b->where('maildispatch_conversations.status', 'cerrada');
                break;
            case 'agent':
                // Team board drill-down: one agent's open workload. $agentId
                // null means the unassigned bucket (the board shows it as a
                // column too, so the dispatcher can hand those out).
                $agentId === null
                    ? $b->where('maildispatch_conversations.agent_id', null)
                    : $b->where('maildispatch_conversations.agent_id', $agentId);
                $b->whereNotIn('maildispatch_conversations.status', ['cerrada', 'autoarchivo', 'autogenerado']);
                // Lo que nadie ha respondido va arriba: es la deuda real del
                // agente. El orderBy común de abajo queda como criterio 2.
                $b->orderBy('maildispatch_conversations.first_response_at IS NULL', 'DESC', false);
                break;
            case 'all':
            default:
                // everything except the auto-triaged buckets, open first
                $b->where('maildispatch_conversations.status !=', 'autoarchivo')
                  ->where('maildispatch_conversations.status !=', 'autogenerado');
                break;
        }

        // Free-text search across subject, requester, GLPI folio and the message
        // bodies. Grouped so the OR set does not break the filter's AND
        // conditions above.
        $q = trim($q);
        if ($q !== '') {
            $b->groupStart()
              ->like('maildispatch_conversations.subject', $q)
              ->orLike('maildispatch_conversations.requester_name', $q)
              ->orLike('maildispatch_conversations.requester_email', $q)
              ->orLike('maildispatch_conversations.glpi_folio', $q);

            // The body lives in another table and is searched through its
            // FULLTEXT index, which resolves to a bounded id list. Joining
            // instead would run one index lookup per candidate row.
            $bodyIds = $this->bodyMatchIds($q);
            if ($bodyIds !== []) {
                $b->orWhereIn('maildispatch_conversations.id', $bodyIds);
            }

            $b->groupEnd();
        }

        // Paginated: the pager is available afterwards via $model->pager. The
        // page number is read from the ?page= query param automatically.
        return $b->orderBy('maildispatch_conversations.last_activity_at', 'DESC')
                 ->paginate($perPage, 'default');
    }

    /**
     * Live workload per agent for the team board: what each agent is holding
     * right now, not a historical aggregate (that is what Métricas is for).
     *
     * "Sin responder" counts open threads the agent owns that never got a first
     * response, which is the queue that actually hurts. `oldest_activity` is the
     * last activity on the agent's stalest thread; turning that into elapsed
     * minutes is the caller's job, since only it knows the service calendar.
     *
     * Agents with nothing open still appear (zeroed) so the board shows who is
     * free; the caller merges this with the active-agent roster.
     *
     * @return array<int,array{agent_id:int,open:int,unanswered:int,pending:int,oldest_activity:string,closed_today:int}>
     */
    public function teamWorkload(): array
    {
        $open = $this->db->table($this->table . ' c')
            ->select('c.agent_id')
            ->select('COUNT(*) AS open_count', false)
            ->select('SUM(CASE WHEN c.first_response_at IS NULL THEN 1 ELSE 0 END) AS unanswered', false)
            ->select("SUM(CASE WHEN c.status = 'esperando_agente' THEN 1 ELSE 0 END) AS pending", false)
            ->select('MIN(c.last_activity_at) AS oldest_activity', false)
            ->whereNotIn('c.status', ['cerrada', 'autoarchivo', 'autogenerado'])
            ->where('c.agent_id IS NOT NULL', null, false)
            ->groupBy('c.agent_id')
            ->get()->getResultArray();

        $rows = [];
        foreach ($open as $r) {
            $rows[(int) $r['agent_id']] = [
                'agent_id'        => (int) $r['agent_id'],
                'open'            => (int) $r['open_count'],
                'unanswered'      => (int) $r['unanswered'],
                'pending'         => (int) $r['pending'],
                'oldest_activity' => (string) ($r['oldest_activity'] ?? ''),
                'closed_today'    => 0,
            ];
        }

        $closed = $this->db->table($this->table . ' c')
            ->select('c.agent_id')
            ->select('COUNT(*) AS closed_count', false)
            ->where('c.status', 'cerrada')
            ->where('c.closed_at >=', date('Y-m-d 00:00:00'))
            ->where('c.agent_id IS NOT NULL', null, false)
            ->groupBy('c.agent_id')
            ->get()->getResultArray();

        foreach ($closed as $r) {
            $id = (int) $r['agent_id'];
            $rows[$id] ??= [
                'agent_id'        => $id,
                'open'            => 0,
                'unanswered'      => 0,
                'pending'         => 0,
                'oldest_activity' => '',
                'closed_today'    => 0,
            ];
            $rows[$id]['closed_today'] = (int) $r['closed_count'];
        }

        return $rows;
    }

    /**
     * Unassigned bucket for the team board: how many are waiting for someone to
     * take them and when the oldest one arrived (the caller converts that into
     * elapsed minutes through the service calendar).
     *
     * @return array{open:int,oldest_received_at:string}
     */
    public function unassignedLoad(): array
    {
        $row = $this->db->table($this->table)
            ->select('COUNT(*) AS open_count', false)
            ->select('MIN(received_at) AS oldest', false)
            ->where('agent_id', null)
            ->whereNotIn('status', ['cerrada', 'autoarchivo', 'autogenerado'])
            ->where('outbound_only', 0)
            ->get()->getRowArray();

        return [
            'open'               => (int) ($row['open_count'] ?? 0),
            'oldest_received_at' => (string) ($row['oldest'] ?? ''),
        ];
    }

    /**
     * How many open threads each agent owns that breached the first-response
     * SLA: still unanswered and received before $receivedBefore. Drives the red
     * state of the agent cards.
     *
     * The caller passes the cutoff instant rather than a number of minutes,
     * because with a service calendar in play "SLA minutes ago" is not simply
     * now minus N — BusinessCalendar::cutoff() resolves it.
     *
     * @return array<int,int> agent id => breached count
     */
    public function slaBreachesByAgent(string $receivedBefore): array
    {
        if (trim($receivedBefore) === '') {
            return [];
        }

        $rows = $this->db->table($this->table . ' c')
            ->select('c.agent_id')
            ->select('COUNT(*) AS breached', false)
            ->whereNotIn('c.status', ['cerrada', 'autoarchivo', 'autogenerado'])
            ->where('c.agent_id IS NOT NULL', null, false)
            ->where('c.first_response_at IS NULL', null, false)
            ->where('c.received_at <', $receivedBefore)
            ->groupBy('c.agent_id')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['agent_id']] = (int) $r['breached'];
        }

        return $out;
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
        return $this->where('agent_id', null)
            ->where('status !=', 'cerrada')
            ->where('outbound_only', 0)
            ->countAllResults();
    }

    /**
     * The two numbers the inbox rail shows next to "Mis métricas": what the
     * agent closed today and what of theirs is already past the first-response
     * SLA. Kept as its own pair of narrow counts instead of reusing the team
     * board, which would run seven queries on the busiest page of the module
     * to answer a question about one person.
     *
     * $slaCutoff is the wall-clock frontier the caller already resolved through
     * the service calendar; empty disables the breach count.
     *
     * @return array{closedToday:int,breached:int}
     */
    public function todayScoreFor(int $userId, string $slaCutoff = ''): array
    {
        $closed = $this->db->table($this->table)
            ->where('agent_id', $userId)
            ->where('status', 'cerrada')
            ->where('closed_at >=', date('Y-m-d 00:00:00'))
            ->countAllResults();

        $breached = 0;
        if (trim($slaCutoff) !== '') {
            $breached = $this->db->table($this->table)
                ->where('agent_id', $userId)
                ->whereNotIn('status', ['cerrada', 'autoarchivo', 'autogenerado'])
                ->where('first_response_at IS NULL', null, false)
                ->where('received_at <', $slaCutoff)
                ->countAllResults();
        }

        return ['closedToday' => $closed, 'breached' => $breached];
    }

    /**
     * Per-tab counts for the inbox badges. Honors the same free-text search as
     * the list, so the numbers match what each tab would show.
     *
     * @return array{unassigned:int,mine:int,all:int,closed:int}
     */
    public function counts(?int $userId, string $q = ''): array
    {
        $q       = trim($q);
        $bodyIds = $q !== '' ? $this->bodyMatchIds($q) : [];

        $search = function ($b) use ($q, $bodyIds) {
            if ($q !== '') {
                $b->groupStart()
                  ->like('subject', $q)
                  ->orLike('requester_name', $q)
                  ->orLike('requester_email', $q)
                  ->orLike('glpi_folio', $q);
                if ($bodyIds !== []) {
                    $b->orWhereIn('id', $bodyIds);
                }
                $b->groupEnd();
            }
            return $b;
        };

        return [
            'unassigned' => $search($this->where('agent_id', null)->where('status !=', 'cerrada')->where('status !=', 'autoarchivo')->where('status !=', 'autogenerado')->where('outbound_only', 0))->countAllResults(),
            'mine'       => $search($this->where('agent_id', $userId)->where('status !=', 'cerrada')->where('status !=', 'autoarchivo')->where('status !=', 'autogenerado'))->countAllResults(),
            'all'        => $search($this->where('status !=', 'autoarchivo')->where('status !=', 'autogenerado'))->countAllResults(),
            // Actionable count = pending verification.
            'autoarchivo' => $search($this->where('status', 'autoarchivo')->where('verified_at', null))->countAllResults(),
            'autogenerado' => $search($this->where('status', 'autogenerado')->groupStart()->whereIn('autogen_state', ['review', 'failed'])->orWhere('verified_at', null)->groupEnd())->countAllResults(),
            'closed'     => $search($this->where('status', 'cerrada'))->countAllResults(),
        ];
    }
}
