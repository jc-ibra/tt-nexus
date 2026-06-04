<?php

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCommunicationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'subject'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'body'         => ['type' => 'LONGTEXT'],
            'from_name'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'from_email'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'       => ['type' => 'ENUM', 'constraint' => ['draft', 'queued', 'sending', 'sent', 'failed'], 'default' => 'draft'],
            'scheduled_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'sent_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'   => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('communications');
    }

    public function down(): void
    {
        $this->forge->dropTable('communications', true);
    }
}
