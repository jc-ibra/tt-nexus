<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('core_app_settings', true);

        // Seed non-sensitive defaults
        $now = date('Y-m-d H:i:s');
        $this->db->table('core_app_settings')->insertBatch([
            ['key' => 'smtp_host',       'value' => '',     'updated_at' => $now],
            ['key' => 'smtp_port',       'value' => '587',  'updated_at' => $now],
            ['key' => 'smtp_crypto',     'value' => 'tls',  'updated_at' => $now],
            ['key' => 'smtp_user',       'value' => '',     'updated_at' => $now],
            ['key' => 'smtp_password',   'value' => '',     'updated_at' => $now],
            ['key' => 'smtp_from_email', 'value' => '',     'updated_at' => $now],
            ['key' => 'smtp_from_name',  'value' => '',     'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('core_app_settings', true);
    }
}
