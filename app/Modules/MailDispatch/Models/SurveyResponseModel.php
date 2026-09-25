<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * Immutable CSAT survey answers. Several rows may share the same token_id —
 * a token now accepts up to `survey_max_responses` answers (a shared quota
 * per conversation, not one-and-done); see SurveyTokenModel::reserveResponseSlot()
 * for how a double answer past the quota is still made impossible.
 */
class SurveyResponseModel extends Model
{
    protected $table         = 'maildispatch_survey_responses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // append-only

    protected $allowedFields = [
        'token_id', 'conversation_id', 'agent_id', 'rating', 'resolved',
        'comment', 'requester_email', 'ip_hash', 'user_agent', 'responded_at',
    ];

    /** Every response of a conversation, oldest first — the order the thread card reads them in. */
    public function allForConversation(int $conversationId): array
    {
        return $this->where('conversation_id', $conversationId)
            ->orderBy('responded_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Supervisor list, joined with the thread's subject and the agent's name.
     *
     * @param array{from?:string,to?:string,agent_id?:int,rating?:int,resolved?:string,q?:string} $f
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function search(array $f, int $page = 1, int $perPage = 50): array
    {
        $total = $this->filteredBuilder($f)->countAllResults(false);

        $rows = $this->listBuilder($f)
            ->orderBy('r.responded_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }

    /** Same filters as search(), unpaginated, capped, for CSV export. */
    public function forCsv(array $f, int $limit = 5000): array
    {
        return $this->listBuilder($f)
            ->orderBy('r.responded_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    /**
     * @param array{from?:string,to?:string,agent_id?:int,rating?:int,resolved?:string,q?:string} $f
     * @return array{sent:int,answered:int,response_rate:float,avg:float,csat:float,
     *               detractors:int,distribution:array<int,int>,resolved:array<string,int>}
     */
    public function stats(array $f): array
    {
        [$fromDt, $toDt] = $this->range($f);

        $answered = $this->filteredBuilder($f)->countAllResults(false);

        // Each aggregate needs its own clean (unselected) builder: mixing r.*
        // from the display select with an aggregate in the same query is what
        // trips MySQL's only_full_group_by.
        $avgRow = $this->filteredBuilder($f)
            ->select('AVG(r.rating) AS avg_rating, SUM(CASE WHEN r.rating >= 4 THEN 1 ELSE 0 END) AS satisfied,'
                . ' SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) AS detractors', false)
            ->get()->getRowArray();

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($this->filteredBuilder($f)->select('r.rating, COUNT(*) AS n', false)->groupBy('r.rating')->get()->getResultArray() as $row) {
            $distribution[(int) $row['rating']] = (int) $row['n'];
        }

        $resolvedCounts = ['yes' => 0, 'partial' => 0, 'no' => 0];
        foreach ($this->filteredBuilder($f)->select('r.resolved, COUNT(*) AS n', false)->groupBy('r.resolved')->get()->getResultArray() as $row) {
            $resolvedCounts[(string) $row['resolved']] = (int) $row['n'];
        }

        // Denominator for the response rate: surveys sent (not necessarily
        // answered) whose last send falls in the same window.
        $db   = \Config\Database::connect();
        $sent = $db->table('maildispatch_survey_tokens')
            ->where('sent_count >', 0)
            ->where('last_sent_at >=', $fromDt)->where('last_sent_at <=', $toDt)
            ->countAllResults();

        $avg = round((float) ($avgRow['avg_rating'] ?? 0), 1);

        return [
            'sent'           => $sent,
            'answered'       => $answered,
            'response_rate'  => $sent > 0 ? round($answered / $sent * 100, 1) : 0.0,
            'avg'            => $avg,
            'csat'           => $answered > 0 ? round(((int) ($avgRow['satisfied'] ?? 0)) / $answered * 100, 1) : 0.0,
            'detractors'     => (int) ($avgRow['detractors'] ?? 0),
            'distribution'   => $distribution,
            'resolved'       => $resolvedCounts,
        ];
    }

    /** Agents with at least one response in range, for the filter dropdown. */
    public function agentsInRange(array $f): array
    {
        $rows = $this->filteredBuilder($f)
            ->select('r.agent_id, u.name AS agent_name', false)
            ->where('r.agent_id IS NOT NULL', null, false)
            ->groupBy('r.agent_id')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['agent_id'], 'name' => (string) ($r['agent_name'] ?? ('Agente #' . $r['agent_id'])),
        ], $rows);
    }

    /** filteredBuilder() + the display select, for search()/forCsv(). */
    private function listBuilder(array $f): \CodeIgniter\Database\BaseBuilder
    {
        return $this->filteredBuilder($f)->select(
            'r.*, c.subject AS conversation_subject, c.requester_name AS requester_name, u.name AS agent_name',
            false
        );
    }

    /**
     * The joins + WHERE filters shared by every query below, with NO select —
     * each caller adds its own (r.* for a list, an aggregate for stats()) so
     * a fresh, unmixed SELECT is built every time. Returns a brand new builder
     * on every call; never reused across two different selects.
     *
     * @param array{from?:string,to?:string,agent_id?:int,rating?:int,resolved?:string,q?:string} $f
     */
    private function filteredBuilder(array $f): \CodeIgniter\Database\BaseBuilder
    {
        [$fromDt, $toDt] = $this->range($f);

        $b = $this->db->table($this->table . ' r')
            ->join('maildispatch_conversations c', 'c.id = r.conversation_id', 'left')
            ->join('core_users u', 'u.id = r.agent_id', 'left')
            ->where('r.responded_at >=', $fromDt)
            ->where('r.responded_at <=', $toDt);

        if (! empty($f['agent_id'])) {
            $b->where('r.agent_id', (int) $f['agent_id']);
        }
        if (! empty($f['rating'])) {
            $b->where('r.rating', (int) $f['rating']);
        }
        if (! empty($f['resolved']) && in_array($f['resolved'], ['yes', 'partial', 'no'], true)) {
            $b->where('r.resolved', $f['resolved']);
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $b->groupStart()
                ->like('c.subject', $q)
                ->orLike('c.requester_name', $q)
                ->orLike('r.comment', $q)
                ->groupEnd();
        }

        return $b;
    }

    /** Normalizes a from/to range; defaults to the last 30 days (mirrors MailDispatchMetrics::range()). */
    private function range(array $f): array
    {
        $from   = $f['from'] ?? null;
        $to     = $f['to'] ?? null;
        $fromDt = $from && strtotime($from) ? date('Y-m-d 00:00:00', strtotime($from)) : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $toDt   = $to && strtotime($to) ? date('Y-m-d 23:59:59', strtotime($to)) : date('Y-m-d 23:59:59');

        return [$fromDt, $toDt];
    }
}
