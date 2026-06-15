<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlpiIdcCanonicalTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'canonical_name'  => ['type' => 'VARCHAR', 'constraint' => 200],
            'normalized_form' => ['type' => 'VARCHAR', 'constraint' => 200],
            'is_verified'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('normalized_form');
        $this->forge->addKey('is_verified');
        $this->forge->createTable('kpi_glpi_idc_canonical');
    }

    public function down(): void
    {
        $this->forge->dropTable('kpi_glpi_idc_canonical', true);
    }
}
