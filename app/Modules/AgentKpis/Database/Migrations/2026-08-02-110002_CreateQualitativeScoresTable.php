<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Per-competency breakdown of the qualitative rubric (8 fixed competencies). */
class CreateQualitativeScoresTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'evaluation_id'   => ['type' => 'INT', 'unsigned' => true],
            'competency_key'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'competency_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'weight'          => ['type' => 'DECIMAL', 'constraint' => '4,2'],
            'score'           => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'evidence'        => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['evaluation_id', 'competency_key']);
        $this->forge->addForeignKey('evaluation_id', 'agent_kpis_monthly_evaluations', 'id', '', 'CASCADE');
        $this->forge->createTable('agent_kpis_qualitative_scores');
    }

    public function down(): void
    {
        $this->forge->dropTable('agent_kpis_qualitative_scores', true);
    }
}
