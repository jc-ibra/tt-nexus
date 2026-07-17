<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Manages servicedesk_backlog_areas: the root GLPI ITIL category -> business
 * area mapping used to split the backlog report into Administración / Operaciones.
 *
 * Keyed by GLPI category id (external id, GLPI is a separate DB). Only rows with
 * a non-empty area actually classify tickets; unmapped roots fall into the
 * report's "Sin clasificar" bucket.
 */
class ServiceDeskBacklogAreaModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Full map: category id => ['area' => string, 'category_name' => string].
     *
     * @return array<int,array{area:string,category_name:string}>
     */
    public function all(): array
    {
        $rows = $this->db->table('servicedesk_backlog_areas')->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['itilcategory_id']] = [
                'area'          => (string) ($r['area'] ?? ''),
                'category_name' => (string) ($r['category_name'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Just the assignments that matter: category id => area key (non-empty only).
     *
     * @return array<int,string>
     */
    public function assignments(): array
    {
        $out = [];
        foreach ($this->all() as $id => $row) {
            if ($row['area'] !== '') {
                $out[$id] = $row['area'];
            }
        }
        return $out;
    }

    /**
     * Upserts the full mapping. $rows is category id => ['area','category_name'].
     * Rows resolving to an empty area are deleted to keep the table lean.
     *
     * @param array<int,array{area:string,category_name:string}> $rows
     */
    public function saveAll(array $rows): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $id => $row) {
            $id   = (int) $id;
            $area = trim((string) ($row['area'] ?? ''));
            $name = trim((string) ($row['category_name'] ?? ''));

            $exists = $this->db->table('servicedesk_backlog_areas')
                ->where('itilcategory_id', $id)->countAllResults();

            if ($area === '') {
                if ($exists) {
                    $this->db->table('servicedesk_backlog_areas')->where('itilcategory_id', $id)->delete();
                }
                continue;
            }

            if ($exists) {
                $this->db->table('servicedesk_backlog_areas')->where('itilcategory_id', $id)->update([
                    'area'          => $area,
                    'category_name' => $name,
                    'updated_at'    => $now,
                ]);
            } else {
                $this->db->table('servicedesk_backlog_areas')->insert([
                    'itilcategory_id' => $id,
                    'area'            => $area,
                    'category_name'   => $name,
                    'updated_at'      => $now,
                ]);
            }
        }
    }
}
