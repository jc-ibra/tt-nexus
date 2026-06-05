<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlpiCoordinatorsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'zone'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'coord_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'gte_name'   => ['type' => 'VARCHAR', 'constraint' => 120],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('zone');
        $this->forge->createTable('glpi_coordinators');
    }

    public function down(): void
    {
        $this->forge->dropTable('glpi_coordinators', true);
    }
}
