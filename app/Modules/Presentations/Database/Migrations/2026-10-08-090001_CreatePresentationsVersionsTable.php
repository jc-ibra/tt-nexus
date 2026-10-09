<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePresentationsVersionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_name'   => ['type' => 'VARCHAR', 'constraint' => 80],
            'size_bytes'    => ['type' => 'BIGINT', 'unsigned' => true],
            'sha256'        => ['type' => 'CHAR', 'constraint' => 64],
            'notes'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'uploaded_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('uploaded_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('presentations_versions');
    }

    public function down(): void
    {
        $this->forge->dropTable('presentations_versions', true);
    }
}
