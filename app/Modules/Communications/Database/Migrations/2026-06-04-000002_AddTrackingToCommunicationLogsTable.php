<?php

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTrackingToCommunicationLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('communication_logs', [
            'tracking_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
                'after'      => 'sent_at',
            ],
            'opened_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'tracking_token',
            ],
        ]);

        $this->db->query('ALTER TABLE communication_logs ADD INDEX idx_tracking_token (tracking_token)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE communication_logs DROP INDEX idx_tracking_token');
        $this->forge->dropColumn('communication_logs', ['tracking_token', 'opened_at']);
    }
}
