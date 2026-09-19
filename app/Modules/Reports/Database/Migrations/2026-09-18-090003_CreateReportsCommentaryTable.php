<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReportsCommentaryTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'snapshot_id' => ['type' => 'INT', 'unsigned' => true],
            'section_key' => ['type' => 'VARCHAR', 'constraint' => 60],
            'body'        => ['type' => 'TEXT'],
            'is_ai_draft' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'author_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['snapshot_id', 'section_key']);
        $this->forge->addForeignKey('snapshot_id', 'reports_snapshots', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('author_id', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('reports_commentary');
    }

    public function down(): void
    {
        $this->forge->dropTable('reports_commentary', true);
    }
}
