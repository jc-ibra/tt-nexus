<?php

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCommunicationListTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'communication_id' => ['type' => 'INT', 'unsigned' => true],
            'list_id'          => ['type' => 'INT', 'unsigned' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['communication_id', 'list_id']);
        $this->forge->addForeignKey('communication_id', 'communications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('list_id', 'recipient_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('communication_list');
    }

    public function down(): void
    {
        $this->forge->dropTable('communication_list', true);
    }
}
