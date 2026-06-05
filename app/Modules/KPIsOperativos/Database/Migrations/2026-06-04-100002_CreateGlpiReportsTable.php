<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlpiReportsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 160],
            'period_start'    => ['type' => 'DATE', 'null' => true],
            'period_end'      => ['type' => 'DATE', 'null' => true],
            'source_type'     => ['type' => 'ENUM', 'constraint' => ['csv', 'xlsx', 'api'], 'default' => 'csv'],
            'source_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'source_path'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'total_tickets'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'kpi_json'        => ['type' => 'LONGTEXT', 'null' => true],
            'pptx_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['processing', 'ready', 'failed'], 'default' => 'processing'],
            'error_message'   => ['type' => 'TEXT', 'null' => true],
            'uploaded_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey(['period_start', 'period_end']);
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('glpi_reports');
    }

    public function down(): void
    {
        $this->forge->dropTable('glpi_reports', true);
    }
}
