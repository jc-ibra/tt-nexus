<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlpiIdcAliasesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'canonical_id'      => ['type' => 'INT', 'unsigned' => true],
            'alias_raw'         => ['type' => 'VARCHAR', 'constraint' => 200],
            'alias_normalized'  => ['type' => 'VARCHAR', 'constraint' => 200],
            'similarity_score'  => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'auto_matched'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'needs_review'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'source_report_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('alias_normalized');
        $this->forge->addKey('canonical_id');
        $this->forge->addKey('needs_review');
        $this->forge->addForeignKey('canonical_id', 'kpi_glpi_idc_canonical', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_report_id', 'kpi_glpi_reports', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('kpi_glpi_idc_aliases');
    }

    public function down(): void
    {
        $this->forge->dropTable('kpi_glpi_idc_aliases', true);
    }
}
