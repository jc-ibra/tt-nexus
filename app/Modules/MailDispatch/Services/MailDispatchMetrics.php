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
            'by_agent'              => $this->byAgent($fromDt, $toDt),
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

    /** Per-agent open count and handled (closed-in-range) count. */
    private function byAgent(string $from, string $to): array
    {
        // Open conversations currently owned by each agent.
        $open = $this->db->table('maildispatch_conversations c')
            ->select('c.agent_id, u.name AS agent_name, COUNT(*) AS open_count')
            ->join('core_users u', 'u.id = c.agent_id', 'inner')
            ->where('c.status !=', 'cerrada')
            ->where('c.agent_id IS NOT NULL', null, false)
            ->groupBy('c.agent_id')
            ->get()->getResultArray();

        $map = [];
        foreach ($open as $r) {
            $map[(int) $r['agent_id']] = [
                'agent_id'   => (int) $r['agent_id'],
                'agent_name' => $r['agent_name'],
                'open'       => (int) $r['open_count'],
                'closed'     => 0,
            ];
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
            if (! isset($map[$id])) {
                $map[$id] = ['agent_id' => $id, 'agent_name' => $r['agent_name'], 'open' => 0, 'closed' => 0];
            }
            $map[$id]['closed'] = (int) $r['closed_count'];
        }

        $out = array_values($map);
        usort($out, static fn($a, $b) => ($b['open'] + $b['closed']) <=> ($a['open'] + $a['closed']));
        return $out;
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

    /** Inbound volume per day (received_at) across the range. */
    private function dailyVolume(string $from, string $to, ?int $agentId): array
    {
        $b = $this->db->table('maildispatch_conversations')
            ->select("DATE(received_at) AS day, COUNT(*) AS total")
            ->where('received_at >=', $from)->where('received_at <=', $to)
            ->groupBy('day')->orderBy('day', 'ASC');
        $this->applyAgent($b, $agentId);
        $rows = $b->get()->getResultArray();

        return array_map(static fn($r) => ['day' => $r['day'], 'total' => (int) $r['total']], $rows);
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
