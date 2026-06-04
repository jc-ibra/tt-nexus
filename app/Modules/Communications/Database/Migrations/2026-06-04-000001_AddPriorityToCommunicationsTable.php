<?php

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPriorityToCommunicationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('communications', [
            'priority' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 3,
                'null'       => false,
                'after'      => 'from_email',
            ],
            'request_read_receipt' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'priority',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('communications', ['priority', 'request_read_receipt']);
    }
}
