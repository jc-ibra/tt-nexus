<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links a Telegram chat to a Nexus employee (and their GLPI user id).
 *
 * One employee = one Telegram account (unique employee_id). The GLPI user id is
 * copied from provisioning_external_accounts at link time so day-to-day ticket
 * queries do not need a cross-module join.
 */
class CreateTechBotTelegramLinksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'telegram_chat_id'    => ['type' => 'BIGINT'],
            'telegram_username'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'telegram_first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'employee_id'         => ['type' => 'INT', 'unsigned' => true],
            'glpi_user_id'        => ['type' => 'INT', 'unsigned' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'verified_at'         => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'          => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('telegram_chat_id');
        $this->forge->addUniqueKey('employee_id');
        $this->forge->addKey('glpi_user_id');
        // employees table carries its module prefix baked into the name.
        $this->forge->addForeignKey('employee_id', 'employees_employees', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('techbot_telegram_links');
    }

    public function down(): void
    {
        $this->forge->dropTable('techbot_telegram_links', true);
    }
}
