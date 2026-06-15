<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvisioningSystemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'key'         => ['type' => 'VARCHAR', 'constraint' => 40],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'base_url'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'auth_type'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'default' => null],
            'options'     => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'notes'       => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('key');
        $this->forge->createTable('provisioning_systems');

        // Seed the three known systems so they're always available.
        $now = date('Y-m-d H:i:s');
        $this->db->table('provisioning_systems')->insertBatch([
            [
                'key'        => 'glpi',
                'name'       => 'GLPI',
                'description'=> 'Sistema de tickets y servicio. Sincroniza identidad para asignación de tickets e indicadores por técnico.',
                'base_url'   => '',
                'auth_type'  => 'glpi_app_user_token',
                'options'    => json_encode([
                    'default_profile_id' => 4,
                    'default_entity_id'  => 0,
                ]),
                'is_active'  => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key'        => 'mailcow',
                'name'       => 'Mailcow',
                'description'=> 'Servidor de correo. La conexión se reutiliza desde el módulo de Buzones.',
                'base_url'   => '',
                'auth_type'  => 'mailcow_settings_reuse',
                'options'    => json_encode([
                    'default_quota_mb' => 1024,
                    'default_domain'   => '',
                ]),
                'is_active'  => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key'        => 'intranet',
                'name'       => 'Intranet',
                'description'=> 'Intranet corporativa. Implementa el contrato definido en el README del módulo.',
                'base_url'   => '',
                'auth_type'  => 'bearer_api_key',
                'options'    => json_encode([]),
                'is_active'  => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('provisioning_systems', true);
    }
}
