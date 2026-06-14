<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMfaColumnsToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'mfa_secret' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
                'after'      => 'status',
            ],
            'mfa_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'mfa_secret',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', ['mfa_secret', 'mfa_enabled']);
    }
}
