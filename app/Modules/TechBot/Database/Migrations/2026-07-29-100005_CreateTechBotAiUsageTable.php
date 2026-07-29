<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Usage log for TechBot's optional Claude formatting. Records every formatting
 * call (tokens in/out + estimated cost) so the panel can show spend, mirroring
 * the servicedesk_ai_usage pattern. `accepted` marks whether the technician kept
 * the AI-formatted text over their original.
 */
class CreateTechBotAiUsageTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'kind'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'format'],
            'model'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'input_tokens'   => ['type' => 'INT', 'default' => 0],
            'output_tokens'  => ['type' => 'INT', 'default' => 0],
            'estimated_cost' => ['type' => 'DECIMAL', 'constraint' => '12,6', 'default' => 0],
            'accepted'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('techbot_ai_usage');
    }

    public function down(): void
    {
        $this->forge->dropTable('techbot_ai_usage', true);
    }
}
