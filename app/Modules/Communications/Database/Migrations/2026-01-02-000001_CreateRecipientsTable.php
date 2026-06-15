<?php

declare(strict_types=1);

namespace App\Modules\Communications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRecipientsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 191],
            'area'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('status');
        $this->forge->createTable('comms_recipients');
    }

    public function down(): void
    {
        $this->forge->dropTable('comms_recipients', true);
    }
}
