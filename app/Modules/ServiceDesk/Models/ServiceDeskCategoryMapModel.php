<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * SuperAdmin-managed mapping over GLPI ITIL categories:
 *   glpi_category_id => { cliente, is_supported }.
 *
 * Used to (a) restrict which categories the import template offers and (b)
 * homologate the ticket title with the mapped CLIENTE.
 */
class ServiceDeskCategoryMapModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * All rows keyed by GLPI category id.
     *
     * @return array<int, array{cliente:string, is_supported:bool, category_name:string}>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->db->table('servicedesk_category_map')->get()->getResultArray() as $row) {
            $out[(int) $row['glpi_category_id']] = [
                'cliente'       => (string) ($row['cliente'] ?? ''),
                'is_supported'  => (int) ($row['is_supported'] ?? 0) === 1,
                'category_name' => (string) ($row['category_name'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Supported GLPI category ids.
     *
     * @return int[]
     */
    public function supportedIds(): array
    {
        $rows = $this->db->table('servicedesk_category_map')
            ->select('glpi_category_id')
            ->where('is_supported', 1)
            ->get()->getResultArray();
        return array_map(static fn($r) => (int) $r['glpi_category_id'], $rows);
    }

    public function hasSupported(): bool
    {
        return $this->db->table('servicedesk_category_map')->where('is_supported', 1)->countAllResults() > 0;
    }

    /**
     * CLIENTE label for a category id (empty string when none).
     */
    public function clienteFor(int $categoryId): string
    {
        $row = $this->db->table('servicedesk_category_map')
            ->select('cliente')
            ->where('glpi_category_id', $categoryId)
            ->get()->getRow();
        return $row ? (string) $row->cliente : '';
    }

    /**
     * category_id => cliente, for all rows with a non-empty cliente.
     *
     * @return array<int,string>
     */
    public function clienteMap(): array
    {
        $out = [];
        foreach ($this->db->table('servicedesk_category_map')->get()->getResultArray() as $row) {
            $cliente = trim((string) ($row['cliente'] ?? ''));
            if ($cliente !== '') {
                $out[(int) $row['glpi_category_id']] = $cliente;
            }
        }
        return $out;
    }

    /**
     * Replaces the full mapping from an admin form.
     *
     * @param array<int, array{cliente?:string, is_supported?:bool, category_name?:string}> $rows
     *        keyed by glpi_category_id
     */
    public function saveAll(array $rows): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $categoryId => $data) {
            $categoryId = (int) $categoryId;
            if ($categoryId <= 0) {
                continue;
            }
            $cliente     = trim((string) ($data['cliente'] ?? ''));
            $isSupported = ! empty($data['is_supported']) ? 1 : 0;
            $name        = (string) ($data['category_name'] ?? '');

            // Drop rows that carry no information (not supported, no cliente).
            if ($isSupported === 0 && $cliente === '') {
                $this->db->table('servicedesk_category_map')->where('glpi_category_id', $categoryId)->delete();
                continue;
            }

            $exists = $this->db->table('servicedesk_category_map')
                ->where('glpi_category_id', $categoryId)->countAllResults();

            if ($exists) {
                $this->db->table('servicedesk_category_map')
                    ->where('glpi_category_id', $categoryId)
                    ->update([
                        'category_name' => $name,
                        'cliente'       => $cliente,
                        'is_supported'  => $isSupported,
                        'updated_at'    => $now,
                    ]);
            } else {
                $this->db->table('servicedesk_category_map')->insert([
                    'glpi_category_id' => $categoryId,
                    'category_name'    => $name,
                    'cliente'          => $cliente,
                    'is_supported'     => $isSupported,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }
}
