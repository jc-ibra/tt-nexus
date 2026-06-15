<?php

declare(strict_types=1);

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRecipientListsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('created_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('comms_recipient_lists');
    }

    public function down(): void
    {
        $this->forge->dropTable('comms_recipient_lists', true);
    }
}
