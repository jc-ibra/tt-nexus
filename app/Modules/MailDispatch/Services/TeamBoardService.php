<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\EventModel;

/**
 * Live picture of who is holding what, for the dispatcher.
 *
 * Métricas answers "how did we do last month" from closed conversations; this
 * answers "what is the team sitting on right now" and is deliberately built
 * only from current state plus the existing audit log — no new tables, no
 * presence tracking.
 *
 * Every agent on the roster appears, including idle ones: seeing who is free
 * is half the reason the dispatcher opens this screen.
 */
class TeamBoardService
{
    /** Agent cards go red past this share of unanswered threads. */
    private const HEAVY_UNANSWERED = 5;

    /**
     * Silence thresholds, in *business* minutes since the agent's last action.
     * Two hours without touching anything is worth a look; half a service day
     * is worth asking about.
     */
    private const SILENT_WARN  = 120;
    private const SILENT_ALERT = 240;

    public function __construct(
        private ConversationModel $conversations,
        private AgentModel $agents,
        private EventModel $events,
        private MailDispatchSettings $settings,
        private BusinessCalendar $calendar
    ) {}

    /**
     * Board payload: the unassigned bucket, one card per agent, and the recent
     * activity feed.
     *
     * @return array{unassigned:array,agents:array<int,array>,activity:array,totals:array}
     */
    public function board(int $activityLimit = 20): array
    {
        $workload = $this->conversations->teamWorkload();
        $slaFirst = $this->settings->slaFirstResponseMinutes();

        // Off the clock the SLA does not run, so the breach frontier is the
        // instant $slaFirst *business* minutes back, not $slaFirst minutes back.
        $breaches = $slaFirst > 0
            ? $this->conversations->slaBreachesByAgent($this->calendar->cutoff($slaFirst))
            : [];

        // "oldestIdle" is about the threads (the stalest one an agent holds);
        // this is about the person (when they last moved anything), which is a
        // different question and the only one an idle agent answers at all.
        $lastAction = $this->events->lastActionByAgent();

        $cards = [];
        foreach ($this->agents->activeAgents() as $agent) {
            $id   = (int) $agent['user_id'];
            $load = $workload[$id] ?? [
                'open'            => 0,
                'unanswered'      => 0,
                'pending'         => 0,
                'oldest_activity' => '',
                'closed_today'    => 0,
            ];
            $breached = $breaches[$id] ?? 0;
            $silence  = $this->silenceFor($lastAction[$id] ?? '');

            $cards[] = [
                'agent_id'     => $id,
                'name'         => (string) ($agent['user_name'] ?? $agent['name'] ?? ''),
                'is_dispatcher' => (bool) ($agent['is_dispatcher'] ?? false),
                'open'         => $load['open'],
                'unanswered'   => $load['unanswered'],
                'pending'      => $load['pending'],
                'oldestIdle'   => $this->calendar->elapsedMinutes($load['oldest_activity'] ?: null),
                'closedToday'  => $load['closed_today'],
                'breached'     => $breached,
                'tone'         => $this->toneFor($load['open'], $load['unanswered'], $breached),
                'lastActionAt' => $silence['at'],
                'silentFor'    => $silence['minutes'],
                'silentTone'   => $silence['tone'],
            ];
        }

        // Busiest first so the dispatcher reads the problem before the calm.
        usort($cards, static function (array $a, array $b): int {
            return [$b['breached'], $b['unanswered'], $b['open']]
               <=> [$a['breached'], $a['unanswered'], $a['open']];
        });

        // Conversations owned by someone who is no longer an active agent would
        // otherwise be invisible on this board, so surface them as a total.
        $rostered = array_column($cards, 'agent_id');
        $orphaned = 0;
        foreach ($workload as $agentId => $load) {
            if (! in_array($agentId, $rostered, true)) {
                $orphaned += $load['open'];
            }
        }

        $unassigned = $this->conversations->unassignedLoad();
        $oldestWait = $this->calendar->elapsedMinutes($unassigned['oldest_received_at'] ?: null);

        return [
            'unassigned' => [
                'open'       => $unassigned['open'],
                'oldestWait' => $oldestWait,
                'tone'       => $this->unassignedTone($oldestWait),
            ],
            'agents'   => $cards,
            'activity' => $this->describeActivity($this->events->recentActivity($activityLimit)),
            'totals'   => [
                'agents'     => count($cards),
                'open'       => array_sum(array_column($cards, 'open')) + $unassigned['open'] + $orphaned,
                'unanswered' => array_sum(array_column($cards, 'unanswered')),
                'breached'   => array_sum(array_column($cards, 'breached')),
                'orphaned'   => $orphaned,
                // How many people have gone quiet long enough to ask about.
                'silent'     => count(array_filter(
                    $cards,
                    static fn (array $c): bool => $c['silentTone'] === 'critical'
                )),
                'silentAfter' => self::SILENT_ALERT,
                'slaFirst'   => $slaFirst,
                // Service calendar context: the board labels its times as
                // business hours only when the clock actually honours it.
                'businessHours'    => $this->calendar->isEnabled(),
                'scheduleSummary'  => $this->calendar->summary(),
                'isOpenNow'        => $this->calendar->isOpenAt(),
            ],
        ];
    }

    /**
     * The same card the team board draws, for a single agent.
     *
     * Built by running the board's own queries and picking one row rather than
     * by writing a second, narrower set: an agent comparing this card with what
     * the dispatcher sees must never find two different numbers.
     *
     * Returns null when the user is not an active agent of the module.
     *
     * @return array{card:array<string,mixed>,context:array<string,mixed>}|null
     */
    public function cardFor(int $userId): ?array
    {
        // Not board(0): findAll(0) means "no limit" in CI4, which would pull
        // the whole audit log for a feed this caller does not even use.
        foreach ($this->board(1)['agents'] as $card) {
            if ($card['agent_id'] === $userId) {
                return [
                    'card'    => $card,
                    'context' => [
                        'slaFirst'        => $this->settings->slaFirstResponseMinutes(),
                        'businessHours'   => $this->calendar->isEnabled(),
                        'scheduleSummary' => $this->calendar->summary(),
                        'minutesPerDay'   => $this->calendar->minutesPerDay(),
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Turns raw audit rows into one readable sentence each. The log stores user
     * ids in from_value/to_value for assignment events, so those are resolved to
     * names in a single extra query.
     *
     * @param  array<int,array<string,mixed>> $events
     * @return array<int,array<string,mixed>>
     */
    private function describeActivity(array $events): array
    {
        $ids = [];
        foreach ($events as $e) {
            foreach (['from_value', 'to_value'] as $k) {
                $v = (string) ($e[$k] ?? '');
                if ($v !== '' && ctype_digit($v)) {
                    $ids[(int) $v] = true;
                }
            }
        }

        $names = [];
        if ($ids !== []) {
            $rows = \Config\Database::connect()->table('core_users')
                ->select('id, name')
                ->whereIn('id', array_keys($ids))
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $names[(int) $r['id']] = (string) $r['name'];
            }
        }

        $nameOf = static fn(?string $v): string => $v !== null && $v !== '' && ctype_digit($v)
            ? ($names[(int) $v] ?? 'un agente')
            : '';

        $out = [];
        foreach ($events as $e) {
            $actor  = (string) ($e['user_name'] ?? '') ?: 'El sistema';
            $target = $nameOf($e['to_value'] ?? null);

            switch ((string) $e['type']) {
                case 'assign':
                    // Self-claim vs handed out by a dispatcher: the log marks the
                    // former with no previous owner and the actor as the target.
                    $verb = ($e['from_value'] === null && (string) ($e['to_value'] ?? '') === (string) ($e['user_id'] ?? ''))
                        ? 'tomó'
                        : 'asignó a ' . $target . ':';
                    break;
                case 'reassign':
                    $verb = 'reasignó a ' . $target . ':';
                    break;
                case 'unassign':
                    $verb = 'liberó';
                    break;
                case 'close':
                    $verb = 'cerró';
                    break;
                case 'reopen':
                    $verb = 'reabrió';
                    break;
                default:
                    $verb = 'movió';
            }

            $out[] = [
                'id'              => (int) $e['id'],
                'conversation_id' => (int) $e['conversation_id'],
                'actor'           => $actor,
                'verb'            => $verb,
                'subject'         => (string) ($e['subject'] ?? '') ?: '(sin asunto)',
                'requester'       => (string) ($e['requester_name'] ?? ''),
                'created_at'      => (string) ($e['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * How long an agent has been silent, measured on the service calendar so a
     * Friday-evening last action does not read as "3 days" on Monday morning.
     *
     * minutes = null means the agent has never registered a single action, so
     * there is no clock to run: the card says so instead of showing a number.
     *
     * @return array{at:string,minutes:int|null,tone:string}
     */
    private function silenceFor(string $lastActionAt): array
    {
        if ($lastActionAt === '') {
            return ['at' => '', 'minutes' => null, 'tone' => 'critical'];
        }

        $minutes = $this->calendar->elapsedMinutes($lastActionAt);

        return [
            'at'      => $lastActionAt,
            'minutes' => $minutes,
            'tone'    => match (true) {
                $minutes >= self::SILENT_ALERT => 'critical',
                $minutes >= self::SILENT_WARN  => 'warning',
                default                        => 'ok',
            },
        ];
    }

    /** Card colour: breaches dominate, then a heavy unanswered pile. */
    private function toneFor(int $open, int $unanswered, int $breached): string
    {
        if ($breached > 0) {
            return 'critical';
        }
        if ($unanswered >= self::HEAVY_UNANSWERED) {
            return 'warning';
        }
        return $open > 0 ? 'info' : 'idle';
    }

    /** The unassigned bucket reddens against the assignment SLA. */
    private function unassignedTone(int $waitMinutes): string
    {
        $sla = $this->settings->slaUnassignedMinutes();
        if ($sla > 0 && $waitMinutes > $sla) {
            return 'critical';
        }
        if ($sla > 0 && $waitMinutes > (int) ($sla / 2)) {
            return 'warning';
        }
        return $waitMinutes > 0 ? 'info' : 'idle';
    }
}
