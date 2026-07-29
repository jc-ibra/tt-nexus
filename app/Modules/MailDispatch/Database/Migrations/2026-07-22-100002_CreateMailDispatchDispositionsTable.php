<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Close-disposition catalog. The SuperAdmin edits these; an agent must pick
 * one to close a conversation. "Ticket GLPI" flags requires_folio so the UI
 * demands the GLPI folio number (reference only — no GLPI integration here).
 */
class CreateMailDispatchDispositionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'requires_folio' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'sort_order'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('maildispatch_dispositions');

        // Seed values required by the spec.
        $now = date('Y-m-d H:i:s');
        $this->db->table('maildispatch_dispositions')->insertBatch([
            ['name' => 'Ticket GLPI',       'requires_folio' => 1, 'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'No requiere ticket', 'requires_folio' => 0, 'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Spam / descartado',  'requires_folio' => 0, 'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Duplicado',          'requires_folio' => 0, 'sort_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_dispositions', true);
    }
}
