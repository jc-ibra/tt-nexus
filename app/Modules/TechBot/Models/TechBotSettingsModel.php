<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Manages the techbot_settings key-value table.
 *
 * Same plain key-value pattern as ServiceDeskSettingsModel. Secrets (bot token,
 * webhook secret) are stored encrypted by the calling service, not here.
 */
class TechBotSettingsModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->table('techbot_settings')->where('key', $key)->get()->getRow();
        return $row ? (string) $row->value : $default;
    }

    public function getAll(): array
    {
        $rows = $this->db->table('techbot_settings')->get()->getResultArray();
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['key']] = (string) $row['value'];
        }
        return $out;
    }

    public function set(string $key, string $value): void
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $this->db->table('techbot_settings')->where('key', $key)->countAllResults();

        if ($existing) {
            $this->db->table('techbot_settings')->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table('techbot_settings')->insert([
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
