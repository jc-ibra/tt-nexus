<?php

declare(strict_types=1);

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateListRecipientTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'list_id'      => ['type' => 'INT', 'unsigned' => true],
            'recipient_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['list_id', 'recipient_id']);
        $this->forge->addForeignKey('list_id',      'recipient_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'recipients',      'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('list_recipient');
    }

    public function down(): void
    {
        $this->forge->dropTable('list_recipient', true);
    }
}
