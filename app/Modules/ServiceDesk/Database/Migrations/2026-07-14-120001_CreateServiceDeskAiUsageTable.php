<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Usage log for the AI ticket creator (Claude API). One row per model call, so
 * the SuperAdmin can measure token consumption, estimated cost and how many
 * tickets the assistant proposed vs. how many were actually created.
 *
 * This is the only new data the AI creator needs: everything else reuses the
 * existing import pipeline (servicedesk_imports) and settings (servicedesk_settings).
 */
class CreateServiceDeskAiUsageTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'kind'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'chat'], // chat | create
            'model'              => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'input_tokens'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'output_tokens'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'cache_read_tokens'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'cache_write_tokens' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'tickets_proposed'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'tickets_created'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'estimated_cost'     => ['type' => 'DECIMAL', 'constraint' => '12,6', 'default' => 0],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('servicedesk_ai_usage');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_ai_usage', true);
    }
}
