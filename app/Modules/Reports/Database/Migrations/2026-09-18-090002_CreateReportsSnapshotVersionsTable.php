<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Keeps the payload a snapshot had BEFORE a SuperAdmin regeneration, so a
 * frozen month that direction already saw can still be audited later.
 */
class CreateReportsSnapshotVersionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'snapshot_id'    => ['type' => 'INT', 'unsigned' => true],
            'payload_json'   => ['type' => 'LONGTEXT', 'null' => true],
            'total_tickets'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'replaced_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('snapshot_id');
        $this->forge->addForeignKey('snapshot_id', 'reports_snapshots', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('replaced_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('reports_snapshot_versions');
    }

    public function down(): void
    {
        $this->forge->dropTable('reports_snapshot_versions', true);
    }
}
