<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\MessageModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Read-only analytics over the dispatch queue for the phase-2 dashboard:
 * backlog, average first-assignment / first-response times, per-agent volume,
 * disposition distribution, and daily inbound volume. All queries honour an
 * optional received_at date range and an optional agent filter, and never
 * mutate anything.
 */
class MailDispatchMetrics
{
    /** Conversations an agent must have answered before winning on speed. */
    private const SPEED_MIN_SAMPLE = 3;

    private BaseConnection $db;

    public function __construct(
        private ConversationModel $conversations,
        private MessageModel $messages,
        private MailDispatchSettings $settings,
        private BusinessCalendar $calendar
    ) {
        $this->db = \Config\Database::connect();
    }

    /** Full dashboard payload. */
    public function dashboard(?string $from, ?string $to, ?int $agentId): array
    {
        [$fromDt, $toDt] = $this->range($from, $to);

        return [
            'range'                 => ['from' => $fromDt, 'to' => $toDt, 'agent_id' => $agentId],
            'backlog_unassigned'    => $this->conversations->countUnassigned(),
            'received'              => $this->countReceived($fromDt, $toDt, $agentId),
            'closed'               => $this->countClosed($fromDt, $toDt, $agentId),
            'avg_first_assignment_min' => $this->avgMinutes('received_at', 'assigned_at', $fromDt, $toDt, $agentId),
            'avg_first_response_min'   => $this->avgMinutes('received_at', 'first_response_at', $fromDt, $toDt, $agentId),
            'by_agent'              => $agents = $this->byAgent($fromDt, $toDt),
            'highlights'            => $this->highlights($agents),
            'dispositions'          => $this->dispositionDistribution($fromDt, $toDt, $agentId),
            'daily_volume'          => $this->dailyVolume($fromDt, $toDt, $agentId),
            'sla'                   => [
                'unassigned_minutes'     => $this->settings->slaUnassignedMinutes(),
                'first_response_minutes' => $this->settings->slaFirstResponseMinutes(),
            ],
            'business_hours'        => [
                'enabled'  => $this->calendar->isEnabled(),
                'schedule' => $this->calendar->summary(),
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Individual metrics
    // -----------------------------------------------------------------------

    private function countReceived(string $from, string $to, ?int $agentId): int
    {
        $b = $this->db->table('maildispatch_conversations')
            ->where('received_at >=', $from)->where('received_at <=', $to);
        $this->applyAgent($b, $agentId);
        return $b->countAllResults();
    }

    private function countClosed(string $from, string $to, ?int $agentId): int
    {
        $b = $this->db->table('maildispatch_conversations')
            ->where('status', 'cerrada')
            ->where('closed_at >=', $from)->where('closed_at <=', $to);
        $this->applyAgent($b, $agentId);
        return $b->countAllResults();
    }

    /**
     * Average minutes between two datetime columns (ignoring nulls).
     *
     * With the service calendar on, the average has to be built in PHP: the
     * elapsed time of each pair depends on the schedule and the holidays, which
     * SQL knows nothing about. Only the two columns are pulled, so the extra
     * cost is one narrow scan of the range instead of an aggregate.
     */
    private function avgMinutes(string $startCol, string $endCol, string $from, string $to, ?int $agentId): ?float
    {
        if (! $this->calendar->isEnabled()) {
            $b = $this->db->table('maildispatch_conversations')
                ->select("AVG(TIMESTAMPDIFF(MINUTE, {$startCol}, {$endCol})) AS avg_min")
                ->where("{$endCol} IS NOT NULL", null, false)
                ->where('received_at >=', $from)->where('received_at <=', $to);
            $this->applyAgent($b, $agentId);
            $row = $b->get()->getRow();
            return $row && $row->avg_min !== null ? round((float) $row->avg_min, 1) : null;
        }

        $b = $this->db->table('maildispatch_conversations')
            ->select("{$startCol} AS start_at, {$endCol} AS end_at")
            ->where("{$endCol} IS NOT NULL", null, false)
            ->where("{$startCol} IS NOT NULL", null, false)
            ->where('received_at >=', $from)->where('received_at <=', $to);
        $this->applyAgent($b, $agentId);
        $rows = $b->get()->getResultArray();

        if ($rows === []) {
            return null;
        }

        $total = 0;
        foreach ($rows as $r) {
            $total += $this->calendar->minutesBetween((string) $r['start_at'], (string) $r['end_at']);
        }

        return round($total / count($rows), 1);
    }

    /**
     * One row per agent with everything the productivity panel reads: what they
     * are holding right now, what they closed in the range, and what they
     * actually did in it.
     *
     * "Open" is current state and "closed" is range-scoped on purpose — the
     * first answers "who is loaded today", the second "who moved work". They
     * are labelled that way in the table so the mix cannot be misread.
     */
    private function byAgent(string $from, string $to): array
    {
        // Open conversations currently owned by each agent. Same exclusions as
        // the team board so "abiertas ahora" and its "en curso" never disagree:
        // auto-archived and auto-generated threads are not work anyone holds.
        $open = $this->db->table('maildispatch_conversations c')
            ->select('c.agent_id, u.name AS agent_name, COUNT(*) AS open_count')
            ->join('core_users u', 'u.id = c.agent_id', 'inner')
            ->whereNotIn('c.status', ['cerrada', 'autoarchivo', 'autogenerado'])
            ->where('c.agent_id IS NOT NULL', null, false)
            ->groupBy('c.agent_id')
            ->get()->getResultArray();

        $blank = [
            'open' => 0, 'closed' => 0, 'actions' => 0, 'replies' => 0,
            'taken' => 0, 'notes' => 0, 'first_response_min' => null, 'first_response_n' => 0,
        ];

        $map = [];
        foreach ($open as $r) {
            $map[(int) $r['agent_id']] = $blank + [
                'agent_id'   => (int) $r['agent_id'],
                'agent_name' => $r['agent_name'],
            ];
            $map[(int) $r['agent_id']]['open'] = (int) $r['open_count'];
        }

        $closed = $this->db->table('maildispatch_conversations c')
            ->select('c.agent_id, u.name AS agent_name, COUNT(*) AS closed_count')
            ->join('core_users u', 'u.id = c.agent_id', 'inner')
            ->where('c.status', 'cerrada')
            ->where('c.closed_at >=', $from)->where('c.closed_at <=', $to)
            ->groupBy('c.agent_id')
            ->get()->getResultArray();

        foreach ($closed as $r) {
            $id = (int) $r['agent_id'];
            $map[$id] ??= $blank + ['agent_id' => $id, 'agent_name' => $r['agent_name']];
            $map[$id]['closed'] = (int) $r['closed_count'];
        }

        foreach ($this->activityByAgent($from, $to) as $id => $act) {
            $map[$id] ??= $blank + ['agent_id' => $id, 'agent_name' => $act['agent_name']];
            $map[$id]['actions'] = $act['actions'];
            $map[$id]['replies'] = $act['replies'];
            $map[$id]['taken']   = $act['taken'];
            $map[$id]['notes']   = $act['notes'];
        }

        foreach ($this->firstResponseByAgent($from, $to) as $id => $avg) {
            if (isset($map[$id])) {
                $map[$id]['first_response_min'] = $avg['avg'];
                $map[$id]['first_response_n']   = $avg['n'];
            }
        }

        $out = array_values($map);
        // Most movement first: this table is read to find who carried the range.
        usort($out, static fn($a, $b) => [$b['actions'], $b['closed'], $b['open']]
                                     <=> [$a['actions'], $a['closed'], $a['open']]);
        return $out;
    }

    /**
     * What each agent actually did in the range, straight from the audit log.
     *
     * A reply is logged as a status transition into 'respondida' by both the
     * Graph and the SMTP sender, which is why replies are counted that way and
     * not from maildispatch_messages — outbound messages carry no author.
     *
     * @return array<int,array{agent_name:string,actions:int,replies:int,taken:int,notes:int}>
     */
    private function activityByAgent(string $from, string $to): array
    {
        $rows = $this->db->table('maildispatch_events e')
            ->select('e.user_id, u.name AS agent_name')
            ->select('COUNT(*) AS actions', false)
            ->select("SUM(CASE WHEN e.type = 'status' AND e.to_value = 'respondida' THEN 1 ELSE 0 END) AS replies", false)
            ->select("SUM(CASE WHEN e.type IN ('assign', 'reassign') THEN 1 ELSE 0 END) AS taken", false)
            ->select("SUM(CASE WHEN e.type = 'note' THEN 1 ELSE 0 END) AS notes", false)
            ->join('core_users u', 'u.id = e.user_id', 'inner')
            ->where('e.created_at >=', $from)->where('e.created_at <=', $to)
            ->groupBy('e.user_id')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']] = [
                'agent_name' => (string) $r['agent_name'],
                'actions'    => (int) $r['actions'],
                'replies'    => (int) $r['replies'],
                'taken'      => (int) $r['taken'],
                'notes'      => (int) $r['notes'],
            ];
        }

        return $out;
    }

    /**
     * Average first-response time per agent over the range, on the service
     * calendar when it is on (same reasoning as avgMinutes: the schedule is not
     * something SQL can apply).
     *
     * @return array<int,array{avg:float,n:int}>
     */
    private function firstResponseByAgent(string $from, string $to): array
    {
        $b = $this->db->table('maildispatch_conversations')
            ->select('agent_id, received_at, first_response_at')
            ->where('agent_id IS NOT NULL', null, false)
            ->where('first_response_at IS NOT NULL', null, false)
            ->where('received_at IS NOT NULL', null, false)
            ->where('received_at >=', $from)->where('received_at <=', $to);

        $totals = [];
        foreach ($b->get()->getResultArray() as $r) {
            $id = (int) $r['agent_id'];
            $totals[$id] ??= ['sum' => 0, 'n' => 0];
            $totals[$id]['sum'] += $this->calendar->isEnabled()
                ? $this->calendar->minutesBetween((string) $r['received_at'], (string) $r['first_response_at'])
                : (int) round((strtotime((string) $r['first_response_at']) - strtotime((string) $r['received_at'])) / 60);
            $totals[$id]['n']++;
        }

        $out = [];
        foreach ($totals as $id => $t) {
            $out[$id] = ['avg' => round($t['sum'] / $t['n'], 1), 'n' => $t['n']];
        }

        return $out;
    }

    /**
     * Who stands out in the range, one agent per question. Deliberately four
     * different questions: the heaviest load, the most movement, the most
     * closures and the fastest first reply — no single "best agent" score,
     * because these four rarely point at the same person and pretending they
     * do would be the wrong tool for a supervisor.
     *
     * The speed award needs a minimum sample so one lucky thread cannot win it.
     *
     * @param  array<int,array<string,mixed>> $agents
     * @return array<string,array<string,mixed>|null>
     */
    private function highlights(array $agents): array
    {
        $best = static function (array $rows, callable $value, bool $lowest = false): ?array {
            $winner = null;
            foreach ($rows as $r) {
                $v = $value($r);
                if ($v === null) {
                    continue;
                }
                if ($winner === null || ($lowest ? $v < $winner['value'] : $v > $winner['value'])) {
                    $winner = ['agent_id' => $r['agent_id'], 'agent_name' => $r['agent_name'], 'value' => $v];
                }
            }

            return $winner !== null && ($lowest || $winner['value'] > 0) ? $winner : null;
        };

        $withSample = array_filter(
            $agents,
            static fn(array $r): bool => $r['first_response_min'] !== null && $r['first_response_n'] >= self::SPEED_MIN_SAMPLE
        );

        $fastest = $best($withSample, static fn(array $r) => $r['first_response_min'], true);
        if ($fastest !== null) {
            foreach ($agents as $r) {
                if ($r['agent_id'] === $fastest['agent_id']) {
                    $fastest['sample'] = $r['first_response_n'];
                }
            }
        }

        return [
            'load'    => $best($agents, static fn(array $r) => $r['open']),
            'actions' => $best($agents, static fn(array $r) => $r['actions']),
            'closed'  => $best($agents, static fn(array $r) => $r['closed']),
            'fastest' => $fastest,
        ];
    }

    /** Distribution of dispositions among conversations closed in range. */
    private function dispositionDistribution(string $from, string $to, ?int $agentId): array
    {
        $b = $this->db->table('maildispatch_conversations c')
            ->select('d.name AS disposition, COUNT(*) AS total')
            ->join('maildispatch_dispositions d', 'd.id = c.disposition_id', 'left')
            ->where('c.status', 'cerrada')
            ->where('c.closed_at >=', $from)->where('c.closed_at <=', $to)
            ->groupBy('c.disposition_id');
        $this->applyAgent($b, $agentId, 'c');
        $rows = $b->get()->getResultArray();

        return array_map(static fn($r) => [
            'disposition' => $r['disposition'] ?? 'Sin disposición',
            'total'       => (int) $r['total'],
        ], $rows);
    }

    /**
     * Inbound volume per day (received_at) across the range.
     *
     * The series is zero-filled: a day with no mail produces no row, and a
     * chart fed only with the rows draws Friday next to Monday as if they were
     * consecutive, which misreads a quiet weekend as sustained volume. Absurdly
     * long ranges fall back to the raw rows so the payload stays bounded.
     */
    private function dailyVolume(string $from, string $to, ?int $agentId): array
    {
        $b = $this->db->table('maildispatch_conversations')
            ->select("DATE(received_at) AS day, COUNT(*) AS total")
            ->where('received_at >=', $from)->where('received_at <=', $to)
            ->groupBy('day')->orderBy('day', 'ASC');
        $this->applyAgent($b, $agentId);
        $rows = $b->get()->getResultArray();

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(string) $r['day']] = (int) $r['total'];
        }

        $start = new \DateTimeImmutable(substr($from, 0, 10));
        $end   = new \DateTimeImmutable(substr($to, 0, 10));
        $span  = (int) $start->diff($end)->days;

        if ($end < $start || $span > 366) {
            return array_map(static fn($r) => ['day' => $r['day'], 'total' => (int) $r['total']], $rows);
        }

        $out = [];
        for ($i = 0; $i <= $span; $i++) {
            $day   = $start->modify("+{$i} days")->format('Y-m-d');
            $out[] = ['day' => $day, 'total' => $byDay[$day] ?? 0];
        }

        return $out;
    }

    // -----------------------------------------------------------------------
    // CSV export
    // -----------------------------------------------------------------------

    public function conversationsCsv(?string $from, ?string $to, ?int $agentId): string
    {
        [$fromDt, $toDt] = $this->range($from, $to);

        $b = $this->db->table('maildispatch_conversations c')
            ->select('c.id, c.subject, c.requester_name, c.requester_email, c.status,'
                . ' u.name AS agent_name, d.name AS disposition, c.glpi_folio,'
                . ' c.received_at, c.assigned_at, c.first_response_at, c.closed_at, c.message_count')
            ->join('core_users u', 'u.id = c.agent_id', 'left')
            ->join('maildispatch_dispositions d', 'd.id = c.disposition_id', 'left')
            ->where('c.received_at >=', $fromDt)->where('c.received_at <=', $toDt)
            ->orderBy('c.received_at', 'ASC');
        $this->applyAgent($b, $agentId, 'c');
        $rows = $b->get()->getResultArray();

        $out  = fopen('php://temp', 'r+');
        fputcsv($out, ['ID', 'Asunto', 'Solicitante', 'Correo', 'Estado', 'Agente', 'Disposición', 'Folio GLPI', 'Recibido', 'Asignado', 'Primera respuesta', 'Cerrado', 'Mensajes']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['subject'], $r['requester_name'], $r['requester_email'], $r['status'],
                $r['agent_name'], $r['disposition'], $r['glpi_folio'],
                $r['received_at'], $r['assigned_at'], $r['first_response_at'], $r['closed_at'], $r['message_count'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        // UTF-8 BOM so Excel opens accents correctly.
        return "\xEF\xBB\xBF" . $csv;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Normalizes a from/to range; defaults to the last 30 days. */
    private function range(?string $from, ?string $to): array
    {
        $fromDt = $from && strtotime($from) ? date('Y-m-d 00:00:00', strtotime($from)) : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $toDt   = $to && strtotime($to) ? date('Y-m-d 23:59:59', strtotime($to)) : date('Y-m-d 23:59:59');
        return [$fromDt, $toDt];
    }

    private function applyAgent($builder, ?int $agentId, string $alias = ''): void
    {
        if ($agentId) {
            $col = ($alias !== '' ? $alias . '.' : '') . 'agent_id';
            $builder->where($col, $agentId);
        }
    }
}
