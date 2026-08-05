<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Auto-triage rules: inbound mail matching a configured sender/subject pattern
 * is routed straight to the "autocierre" status so known noise never reaches the
 * main queue. Such conversations stay visible in their own bucket and any agent
 * can mark them verified (recorded) or move them back to the normal inbox.
 *
 * Adds:
 *  - maildispatch_rules            (admin-managed match rules)
 *  - conversations.auto_rule_id    (which rule triggered the autocierre)
 *  - conversations.verified_by     (agent who signed off, nullable)
 *  - conversations.verified_at     (when it was verified, nullable)
 */
class CreateMailDispatchRulesAndAutoclose extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('maildispatch_rules')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'            => ['type' => 'VARCHAR', 'constraint' => 150],
                // Match on the original sender (email or "@domain"); empty = any.
                'sender_pattern'  => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                // Match on the subject (case-insensitive substring); empty = any.
                'subject_pattern' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order'      => ['type' => 'INT', 'default' => 0],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('is_active');
            $this->forge->createTable('maildispatch_rules');
        }

        $fields = [];
        if (! $this->db->fieldExists('auto_rule_id', 'maildispatch_conversations')) {
            $fields['auto_rule_id'] = ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'disposition_id'];
        }
        if (! $this->db->fieldExists('verified_by', 'maildispatch_conversations')) {
            $fields['verified_by'] = ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'auto_rule_id'];
        }
        if (! $this->db->fieldExists('verified_at', 'maildispatch_conversations')) {
            $fields['verified_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'verified_by'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('maildispatch_conversations', $fields);
        }
    }

    public function down(): void
    {
        foreach (['verified_at', 'verified_by', 'auto_rule_id'] as $col) {
            if ($this->db->fieldExists($col, 'maildispatch_conversations')) {
                $this->forge->dropColumn('maildispatch_conversations', $col);
            }
        }
        $this->forge->dropTable('maildispatch_rules', true);
    }
}
