<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReportsDeliveriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'snapshot_id' => ['type' => 'INT', 'unsigned' => true],
            'recipients'  => ['type' => 'TEXT'],
            'status'      => ['type' => 'ENUM', 'constraint' => ['sent', 'failed'], 'default' => 'sent'],
            'error'       => ['type' => 'TEXT', 'null' => true],
            'sent_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'sent_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('snapshot_id');
        $this->forge->addForeignKey('snapshot_id', 'reports_snapshots', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sent_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('reports_deliveries');
    }

    public function down(): void
    {
        $this->forge->dropTable('reports_deliveries', true);
    }
}
