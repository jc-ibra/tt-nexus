<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserInvitationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 191],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'invited_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'accepted_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'sent_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('user_id');
        $this->forge->addKey('email');
        $this->forge->addForeignKey('user_id', 'core_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('core_user_invitations');
    }

    public function down(): void
    {
        $this->forge->dropTable('core_user_invitations', true);
    }
}
