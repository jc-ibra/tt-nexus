<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Consolidates conversations that are actually the same thread but were split
 * (typically forwarded chains whose In-Reply-To pointed at external ids). Groups
 * conversations by shared reference tokens (maildispatch_message_refs) and merges
 * each group into a single survivor.
 *
 * Survivor = the assigned conversation with the earliest assignment; if none is
 * assigned, the oldest by received_at. The survivor keeps its agent; agents of
 * the other conversations are recorded in the survivor's audit log.
 *
 *   php spark maildispatch:merge-threads            # apply
 *   php spark maildispatch:merge-threads --dry-run  # preview only
 */
class MergeThreads extends BaseCommand
{
    protected $group       = 'MailDispatch';
    protected $name        = 'maildispatch:merge-threads';
    protected $description = 'Fusiona conversaciones duplicadas del mismo hilo (por referencias compartidas).';
    protected $usage       = 'maildispatch:merge-threads [--dry-run]';
    protected $options     = ['--dry-run' => 'Muestra lo que haría sin escribir.'];

    private $db;

    public function run(array $params): void
    {
        $dry = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');
        $this->db = Database::connect();

        $groups = $this->buildGroups();
        $groups = array_filter($groups, static fn (array $g) => count($g) > 1);

        if ($groups === []) {
            CLI::write('No se encontraron conversaciones duplicadas por hilo compartido.', 'green');
            return;
        }

        CLI::write(sprintf('%d grupo(s) de duplicados encontrados.%s', count($groups), $dry ? ' (dry-run)' : ''), 'cyan');
        $mergedTotal = 0;

        foreach ($groups as $convIds) {
            sort($convIds);
            $rows = $this->db->table('maildispatch_conversations')
                ->select('id, agent_id, assigned_at, status, received_at, subject')
                ->whereIn('id', $convIds)->get()->getResultArray();

            $survivor = $this->pickSurvivor($rows);
            $others   = array_values(array_filter($convIds, static fn ($id) => (int) $id !== $survivor));

            $subject = '';
            $agents  = [];
            foreach ($rows as $r) {
                if ((int) $r['id'] === $survivor) {
                    $subject = (string) $r['subject'];
                }
                if ($r['agent_id'] !== null) {
                    $agents[(int) $r['id']] = (int) $r['agent_id'];
                }
            }

            CLI::write(sprintf('  Hilo «%s»', mb_substr($subject, 0, 50)), 'white');
            CLI::write(sprintf('    superviviente: #%d · fusiona: %s', $survivor, implode(', ', array_map(static fn ($i) => '#' . $i, $others))), 'dark_gray');

            if ($dry) {
                $mergedTotal += count($others);
                continue;
            }

            $this->mergeGroup($survivor, $others, $agents);
            $mergedTotal += count($others);
        }

        CLI::write(sprintf('%s %d conversación(es) %s en %d hilo(s).',
            $dry ? 'Se fusionarían' : 'Fusionadas',
            $mergedTotal,
            $dry ? '' : 'consolidadas',
            count($groups)
        ), 'green');
    }

    // -----------------------------------------------------------------------
    // Grouping (union-find over shared ref tokens)
    // -----------------------------------------------------------------------

    /** @return array<int,int[]> connected components of conversation ids. */
    private function buildGroups(): array
    {
        $rows = $this->db->table('maildispatch_message_refs r')
            ->select('r.ref_id, m.conversation_id')
            ->join('maildispatch_messages m', 'm.id = r.message_id')
            ->get()->getResultArray();

        // ref_id -> list of conversation ids that carry it.
        $byRef = [];
        foreach ($rows as $r) {
            $byRef[$r['ref_id']][] = (int) $r['conversation_id'];
        }

        $parent = [];
        $find = static function (int $x) use (&$parent, &$find): int {
            while (($parent[$x] ?? $x) !== $x) {
                $parent[$x] = $parent[$parent[$x]] ?? $parent[$x];
                $x = $parent[$x];
            }
            return $x;
        };
        $union = static function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        };

        foreach ($byRef as $convIds) {
            $convIds = array_values(array_unique($convIds));
            $parent[$convIds[0]] ??= $convIds[0];
            for ($i = 1, $n = count($convIds); $i < $n; $i++) {
                $parent[$convIds[$i]] ??= $convIds[$i];
                $union($convIds[0], $convIds[$i]);
            }
        }

        $components = [];
        foreach (array_keys($parent) as $id) {
            $components[$find($id)][] = $id;
        }
        return array_values($components);
    }

    // -----------------------------------------------------------------------
    // Merge
    // -----------------------------------------------------------------------

    /** Assigned + earliest assignment wins; else oldest by received_at. */
    private function pickSurvivor(array $rows): int
    {
        $assigned = array_filter($rows, static fn ($r) => $r['agent_id'] !== null);
        $pool = $assigned !== [] ? $assigned : $rows;

        usort($pool, static function ($a, $b) use ($assigned) {
            if ($assigned !== []) {
                $ta = strtotime((string) ($a['assigned_at'] ?? '')) ?: PHP_INT_MAX;
                $tb = strtotime((string) ($b['assigned_at'] ?? '')) ?: PHP_INT_MAX;
                if ($ta !== $tb) {
                    return $ta <=> $tb;
                }
            } else {
                $ta = strtotime((string) ($a['received_at'] ?? '')) ?: PHP_INT_MAX;
                $tb = strtotime((string) ($b['received_at'] ?? '')) ?: PHP_INT_MAX;
                if ($ta !== $tb) {
                    return $ta <=> $tb;
                }
            }
            return (int) $a['id'] <=> (int) $b['id'];
        });

        return (int) $pool[0]['id'];
    }

    private function mergeGroup(int $survivor, array $others, array $agents): void
    {
        $this->db->transStart();

        foreach ($others as $other) {
            $this->db->table('maildispatch_messages')->where('conversation_id', $other)->update(['conversation_id' => $survivor]);
            $this->db->table('maildispatch_events')->where('conversation_id', $other)->update(['conversation_id' => $survivor]);
            $this->db->table('maildispatch_attachments')->where('conversation_id', $other)->update(['conversation_id' => $survivor]);
            $this->db->table('maildispatch_conversations')->where('id', $other)->delete();
        }

        $this->recomputeAggregates($survivor);

        // Audit note on the survivor.
        $lostAgents = [];
        foreach ($agents as $cid => $aid) {
            if ($cid !== $survivor && ($agents[$survivor] ?? null) !== $aid) {
                $lostAgents[$aid] = true;
            }
        }
        $note = 'Fusionadas ' . count($others) . ' conversación(es) duplicada(s) (' .
            implode(', ', array_map(static fn ($i) => '#' . $i, $others)) . ') por hilo compartido.';
        if ($lostAgents !== []) {
            $names = $this->agentNames(array_keys($lostAgents));
            $note .= ' Agente(s) previo(s) de las otras: ' . implode(', ', $names) . '.';
        }
        $this->db->table('maildispatch_events')->insert([
            'conversation_id' => $survivor,
            'type'            => 'note',
            'user_id'         => null,
            'note'            => $note,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();
    }

    /** Recomputes counters, dates and reopens the survivor if it was closed. */
    private function recomputeAggregates(int $survivor): void
    {
        $agg = $this->db->table('maildispatch_messages')
            ->select('COUNT(*) AS c, MIN(received_at) AS first_at, MAX(received_at) AS last_at,'
                . " MIN(CASE WHEN direction='out' THEN received_at END) AS first_out")
            ->where('conversation_id', $survivor)->get()->getRowArray();

        $conv = $this->db->table('maildispatch_conversations')->where('id', $survivor)->get()->getRowArray();

        $data = [
            'message_count'    => (int) ($agg['c'] ?? 0),
            'received_at'      => $agg['first_at'] ?? $conv['received_at'],
            'last_activity_at' => $agg['last_at'] ?? $conv['last_activity_at'],
        ];
        if (empty($conv['first_response_at']) && ! empty($agg['first_out'])) {
            $data['first_response_at'] = $agg['first_out'];
        }
        // A closed survivor that absorbed newer activity is reopened.
        if ((string) $conv['status'] === 'cerrada') {
            $data['status']    = $conv['agent_id'] !== null ? 'esperando_agente' : 'nueva';
            $data['closed_at'] = null;
        }

        $this->db->table('maildispatch_conversations')->where('id', $survivor)->update($data);
    }

    private function agentNames(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $rows = $this->db->table('core_users')->select('name')->whereIn('id', $userIds)->get()->getResultArray();
        return array_map(static fn ($r) => (string) $r['name'], $rows);
    }
}
