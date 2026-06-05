<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlpiTicketsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'report_id'       => ['type' => 'INT', 'unsigned' => true],
            'glpi_id'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'titulo'          => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'estado'          => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'fecha_apertura'  => ['type' => 'DATETIME', 'null' => true],
            'fecha_cierre'    => ['type' => 'DATETIME', 'null' => true],
            'regional'        => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'estado_geo'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'municipio'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'proyecto'        => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'categoria'       => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'solicitud'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'idc'             => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'urgencia'        => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'impacto'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'sucursal'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'cliente'         => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'horas_resolucion' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['report_id', 'estado']);
        $this->forge->addKey(['report_id', 'regional']);
        $this->forge->addKey(['report_id', 'estado_geo']);
        $this->forge->addKey(['report_id', 'idc']);
        $this->forge->addKey(['report_id', 'categoria']);
        $this->forge->addKey(['report_id', 'proyecto']);
        $this->forge->addForeignKey('report_id', 'glpi_reports', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('glpi_tickets');
    }

    public function down(): void
    {
        $this->forge->dropTable('glpi_tickets', true);
    }
}
