<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Manages the helpdesk_supervisor_settings key-value table. Same shape as
 * ServiceDeskSettingsModel: plain key -> value; secret values are stored as
 * ciphertext by the accessor (HelpdeskSupervisorSettings), not flagged here.
 */
class HelpdeskSupervisorSettingsModel
{
    private BaseConnection $db;

    private const TABLE = 'helpdesk_supervisor_settings';

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->table(self::TABLE)->where('key', $key)->get()->getRow();
        return $row ? (string) $row->value : $default;
    }

    public function getAll(): array
    {
        $rows = $this->db->table(self::TABLE)->get()->getResultArray();
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['key']] = (string) $row['value'];
        }
        return $out;
    }

    public function set(string $key, string $value): void
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $this->db->table(self::TABLE)->where('key', $key)->countAllResults();

        if ($existing) {
            $this->db->table(self::TABLE)->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table(self::TABLE)->insert([
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
