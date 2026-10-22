<?php

declare(strict_types=1);

namespace App\Modules\Shares\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSharesFilesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'owner_id'      => ['type' => 'INT', 'unsigned' => true],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_name'   => ['type' => 'VARCHAR', 'constraint' => 80],
            'size_bytes'    => ['type' => 'BIGINT', 'unsigned' => true],
            'sha256'        => ['type' => 'CHAR', 'constraint' => 64],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('owner_id');
        $this->forge->addForeignKey('owner_id', 'core_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shares_files');
    }

    public function down(): void
    {
        $this->forge->dropTable('shares_files', true);
    }
}
