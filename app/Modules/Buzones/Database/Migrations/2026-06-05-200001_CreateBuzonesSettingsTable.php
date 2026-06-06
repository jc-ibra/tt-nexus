<?php

declare(strict_types=1);

namespace App\Modules\Buzones\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBuzonesSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('buzones_settings');

        // Seed default rows so updates always work (no insert needed later)
        $now = date('Y-m-d H:i:s');
        $this->db->table('buzones_settings')->insertBatch([
            ['key' => 'mailcow_url',        'value' => '', 'updated_at' => $now],
            ['key' => 'mailcow_api_key',    'value' => '', 'updated_at' => $now],
            ['key' => 'mailcow_verify_ssl', 'value' => '1', 'updated_at' => $now],
            ['key' => 'default_quota_mb',   'value' => '1024', 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('buzones_settings', true);
    }
}
