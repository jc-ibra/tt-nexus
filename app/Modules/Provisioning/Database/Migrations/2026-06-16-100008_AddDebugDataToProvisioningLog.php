<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDebugDataToProvisioningLog extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('provisioning_log', [
            'debug_data' => [
                'type'       => 'MEDIUMTEXT',
                'null'       => true,
                'default'    => null,
                'after'      => 'error_code',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('provisioning_log', 'debug_data');
    }
}
