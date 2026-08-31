<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLastLoginToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('core_users', [
            'last_login_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'mfa_enabled',
            ],
            'last_login_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
                'default'    => null,
                'after'      => 'last_login_at',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('core_users', ['last_login_at', 'last_login_ip']);
    }
}
