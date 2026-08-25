<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Read/write access to the assignment matrix (who answers which category, at
 * which stage, through which channel).
 *
 * The matrix is replaced wholesale on every upload, but the agent -> Nexus user
 * mapping is keyed by name and survives, so the SuperAdmin maps each person
 * once and re-uploads the sheet as often as the roster changes.
 */
class ServiceDeskAssignmentModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // -----------------------------------------------------------------------
    // Agents
    // -----------------------------------------------------------------------

    /**
     * Every agent in the matrix, in sheet order, with their Nexus user.
     *
     * @return list<array{id:int, name:string, user_id:int|null, user_name:string}>
     */
    public function agents(): array
    {
        $rows = $this->db->table('servicedesk_assignment_agents a')
            ->select('a.id, a.name, a.user_id, u.name AS user_name')
            ->join('core_users u', 'u.id = a.user_id', 'left')
            ->orderBy('a.sort_order', 'ASC')
            ->orderBy('a.id', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn(array $r): array => [
            'id'        => (int) $r['id'],
            'name'      => (string) $r['name'],
            'user_id'   => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'user_name' => (string) ($r['user_name'] ?? ''),
        ], $rows);
    }

    /**
     * The matrix agent a Nexus user is mapped to, or null when unmapped.
     *
     * @return array{id:int, name:string}|null
     */
    public function agentForUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $row = $this->db->table('servicedesk_assignment_agents')
            ->select('id, name')
            ->where('user_id', $userId)
            ->get()->getRow();

        return $row ? ['id' => (int) $row->id, 'name' => (string) $row->name] : null;
    }

    /**
     * Sets (or clears) the Nexus user behind each matrix agent.
     *
     * A Nexus user may back only one agent, so claiming a user detaches it from
     * whoever held it before; otherwise "solo lo mío" would be ambiguous.
     *
     * @param array<int, int> $map agent_id => user_id (0 clears the mapping)
     */
    public function saveAgentUsers(array $map): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($map as $agentId => $userId) {
            $agentId = (int) $agentId;
            $userId  = (int) $userId;
            if ($agentId <= 0) {
                continue;
            }
            if ($userId > 0) {
                $this->db->table('servicedesk_assignment_agents')
                    ->where('user_id', $userId)
                    ->where('id !=', $agentId)
                    ->update(['user_id' => null, 'updated_at' => $now]);
            }
            $this->db->table('servicedesk_assignment_agents')
                ->where('id', $agentId)
                ->update([
                    'user_id'    => $userId > 0 ? $userId : null,
                    'updated_at' => $now,
                ]);
        }
    }

    // -----------------------------------------------------------------------
    // Matrix
    // -----------------------------------------------------------------------

    /**
     * The whole matrix, one entry per category in sheet order:
     *   [ 'category_name', 'glpi_category_id', 'cells' => [agentId => [stage => channel]] ]
     *
     * @return list<array{category_name:string, glpi_category_id:int|null, cells:array<int, array<string,string>>}>
     */
    public function matrix(): array
    {
        $rows = $this->db->table('servicedesk_assignments')
            ->orderBy('row_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $name = (string) $r['category_name'];
            if (! isset($out[$name])) {
                $out[$name] = [
                    'category_name'    => $name,
                    'glpi_category_id' => $r['glpi_category_id'] !== null ? (int) $r['glpi_category_id'] : null,
                    'cells'            => [],
                ];
            }
            $out[$name]['cells'][(int) $r['agent_id']][(string) $r['stage']] = (string) $r['channel'];
        }

        return array_values($out);
    }

    /** Number of categories currently in the matrix. */
    public function categoryCount(): int
    {
        $row = $this->db->query(
            'SELECT COUNT(DISTINCT category_name) AS n FROM servicedesk_assignments'
        )->getRow();
        return (int) ($row->n ?? 0);
    }

    /** Number of filled cells currently in the matrix. */
    public function cellCount(): int
    {
        return $this->db->table('servicedesk_assignments')->countAllResults();
    }

    /**
     * Replaces the entire matrix with a freshly parsed sheet.
     *
     * Agents are matched by name: an existing name keeps its id and its user_id
     * mapping, a new name is created, and a name that disappeared from the sheet
     * is deleted along with its cells.
     *
     * @param list<string> $agentNames in sheet order
     * @param list<array{category_name:string, glpi_category_id:int|null, row_order:int,
     *                   agent:string, stage:string, channel:string}> $cells
     */
    public function replaceAll(array $agentNames, array $cells): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->transStart();

        // 1. Reconcile the agent roster, preserving user_id by name.
        $existing = [];
        foreach ($this->db->table('servicedesk_assignment_agents')->get()->getResultArray() as $row) {
            $existing[$this->agentKey((string) $row['name'])] = (int) $row['id'];
        }

        $idByName = [];
        foreach ($agentNames as $i => $name) {
            $key = $this->agentKey($name);
            if (isset($existing[$key])) {
                $id = $existing[$key];
                $this->db->table('servicedesk_assignment_agents')->where('id', $id)->update([
                    'name'       => $name,
                    'sort_order' => $i,
                    'updated_at' => $now,
                ]);
                unset($existing[$key]);
            } else {
                $this->db->table('servicedesk_assignment_agents')->insert([
                    'name'       => $name,
                    'user_id'    => null,
                    'sort_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $this->db->insertID();
            }
            $idByName[$key] = $id;
        }

        // Agents no longer named in the sheet leave the matrix with their cells.
        if ($existing !== []) {
            $goneIds = array_values($existing);
            $this->db->table('servicedesk_assignments')->whereIn('agent_id', $goneIds)->delete();
            $this->db->table('servicedesk_assignment_agents')->whereIn('id', $goneIds)->delete();
        }

        // 2. Rebuild the cells. emptyTable() (DELETE) and not truncate(), which
        // would commit the transaction implicitly on MySQL and defeat the rollback.
        $this->db->table('servicedesk_assignments')->emptyTable();

        $batch = [];
        foreach ($cells as $cell) {
            $agentId = $idByName[$this->agentKey($cell['agent'])] ?? 0;
            if ($agentId <= 0) {
                continue;
            }
            $batch[] = [
                'category_name'    => $cell['category_name'],
                'glpi_category_id' => $cell['glpi_category_id'],
                'row_order'        => $cell['row_order'],
                'agent_id'         => $agentId,
                'stage'            => $cell['stage'],
                'channel'          => $cell['channel'],
                'created_at'       => $now,
            ];
            if (count($batch) >= 200) {
                $this->db->table('servicedesk_assignments')->insertBatch($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->db->table('servicedesk_assignments')->insertBatch($batch);
        }

        $this->db->transComplete();
    }

    /** Case/space-insensitive key so "Juan Carlos" and "juan  carlos" are one agent. */
    private function agentKey(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }
}
