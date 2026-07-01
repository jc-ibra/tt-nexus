<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Persists Nexus-only presentation preferences for GLPI dropdown catalogs
 * (custom label, classification/group and hidden flag). Lives in the Nexus DB
 * (tt_nexus); it NEVER writes to GLPI.
 */
class GlpiCatalogPrefModel
{
    private const T = 'provisioning_glpi_catalog_prefs';

    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /** @return array<string, array{table_name:string,label:?string,classification:?string,is_hidden:string}> */
    public function all(): array
    {
        $out = [];
        foreach ($this->db->table(self::T)->get()->getResultArray() as $r) {
            $out[$r['table_name']] = $r;
        }
        return $out;
    }

    public function upsert(string $table, ?string $label, ?string $classification, bool $hidden): void
    {
        $data = [
            'label'          => ($label ?? '') !== '' ? $label : null,
            'classification' => ($classification ?? '') !== '' ? $classification : null,
            'is_hidden'      => $hidden ? 1 : 0,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $exists = $this->db->table(self::T)->where('table_name', $table)->countAllResults();
        if ($exists) {
            $this->db->table(self::T)->where('table_name', $table)->update($data);
        } else {
            $this->db->table(self::T)->insert(array_merge(['table_name' => $table], $data));
        }
    }

    public function delete(string $table): void
    {
        $this->db->table(self::T)->where('table_name', $table)->delete();
    }
}
