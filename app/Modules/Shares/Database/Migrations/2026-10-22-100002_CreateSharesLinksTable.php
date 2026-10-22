<?php

declare(strict_types=1);

namespace App\Modules\Shares\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSharesLinksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'file_id'        => ['type' => 'INT', 'unsigned' => true],
            'token'          => ['type' => 'VARCHAR', 'constraint' => 43],
            'label'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'expires_at'     => ['type' => 'DATETIME', 'null' => true],
            'max_views'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'view_count'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'password_hash'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_viewed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('file_id');
        $this->forge->addForeignKey('file_id', 'shares_files', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shares_links');
    }

    public function down(): void
    {
        $this->forge->dropTable('shares_links', true);
    }
}
