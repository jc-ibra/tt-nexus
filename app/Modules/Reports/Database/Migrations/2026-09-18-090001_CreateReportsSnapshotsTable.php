<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReportsSnapshotsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'period_year'     => ['type' => 'SMALLINT', 'unsigned' => true],
            'period_month'    => ['type' => 'TINYINT', 'unsigned' => true],
            'period_start'    => ['type' => 'DATE'],
            'period_end'      => ['type' => 'DATE'],
            'status'          => ['type' => 'ENUM', 'constraint' => ['processing', 'ready', 'failed'], 'default' => 'processing'],
            'sections'        => ['type' => 'TEXT', 'null' => true],
            'payload_json'    => ['type' => 'LONGTEXT', 'null' => true],
            'total_tickets'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'pptx_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'xlsx_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'error_message'   => ['type' => 'TEXT', 'null' => true],
            'generated_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'regenerated_at'  => ['type' => 'DATETIME', 'null' => true],
            'regenerated_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['period_year', 'period_month']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('generated_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('regenerated_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('reports_snapshots');
    }

    public function down(): void
    {
        $this->forge->dropTable('reports_snapshots', true);
    }
}
