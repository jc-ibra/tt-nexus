<?php

declare(strict_types=1);

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTrackingToCommunicationLogsTable extends Migration
{
    public function up(): void
    {
        // Idempotent: tracking columns are included in the base migration for fresh installs.
        // This migration only runs retroactively on databases created before the column was added.
        $exists = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'comms_communication_logs'
               AND COLUMN_NAME = 'tracking_token'"
        )->getRow()->cnt ?? 0;

        if ((int) $exists > 0) {
            return;
        }

        $this->forge->addColumn('comms_communication_logs', [
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

        $this->db->query('ALTER TABLE comms_communication_logs ADD INDEX idx_tracking_token (tracking_token)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE comms_communication_logs DROP INDEX IF EXISTS idx_tracking_token');
        $this->forge->dropColumn('comms_communication_logs', ['tracking_token', 'opened_at']);
    }
}
