<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Manages the servicedesk_settings key-value table.
 *
 * Plain key-value (no encrypted keys here — these are operational limits, not
 * secrets). GLPI credentials are reused from Provisioning, never duplicated.
 */
class ServiceDeskSettingsModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->table('servicedesk_settings')->where('key', $key)->get()->getRow();
        return $row ? (string) $row->value : $default;
    }

    public function getAll(): array
    {
        $rows = $this->db->table('servicedesk_settings')->get()->getResultArray();
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['key']] = (string) $row['value'];
        }
        return $out;
    }

    public function set(string $key, string $value): void
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $this->db->table('servicedesk_settings')->where('key', $key)->countAllResults();

        if ($existing) {
            $this->db->table('servicedesk_settings')->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table('servicedesk_settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'updated_at' => $now,
            ]);
        }
    }

    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, (string) $value);
        }
    }
}
