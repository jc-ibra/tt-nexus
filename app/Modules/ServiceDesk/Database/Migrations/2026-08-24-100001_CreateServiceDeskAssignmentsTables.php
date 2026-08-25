<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Assignment matrix: which agent answers which ITIL category, at which stage
 * of the ticket (apertura / documentación / cierre) and through which channel.
 *
 * The source of truth is an .xlsx the SuperAdmin uploads (docs/asignaciones.xlsx
 * and its successors); the importer replaces the whole matrix on every upload.
 * Agents read it from /servicedesk/asignaciones. Nobody downloads the file.
 *
 * Two tables so the agent -> Nexus user mapping survives a re-upload: agents
 * are matched by name and keep their user_id, while every cell is rebuilt.
 */
class CreateServiceDeskAssignmentsTables extends Migration
{
    public function up(): void
    {
        // One row per person named in the sheet's top header.
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            // Nexus user this name belongs to, so the agent can filter "lo mío".
            // Null while the SuperAdmin has not mapped it yet.
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('user_id');
        $this->forge->createTable('servicedesk_assignment_agents');

        // One row per non-empty cell of the matrix.
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // GLPI completename as written in the sheet ("OP > CE > Banorte").
            'category_name'    => ['type' => 'VARCHAR', 'constraint' => 255],
            // Best-effort match against servicedesk_category_map; null when the
            // category is not (yet) known to Nexus. Display never depends on it.
            'glpi_category_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            // Preserves the sheet's row order so the table reads like the file.
            'row_order'        => ['type' => 'INT', 'default' => 0],
            'agent_id'         => ['type' => 'INT', 'unsigned' => true],
            // AV | A | D | C  (apertura x viáticos, apertura, documentación, cierre)
            'stage'            => ['type' => 'VARCHAR', 'constraint' => 8],
            // Channel code(s) as written: E, W, I, "E / W", N/A, ...
            'channel'          => ['type' => 'VARCHAR', 'constraint' => 40],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('agent_id');
        $this->forge->addKey('category_name');
        $this->forge->addKey(['row_order', 'agent_id']);
        $this->forge->createTable('servicedesk_assignments');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_assignments', true);
        $this->forge->dropTable('servicedesk_assignment_agents', true);
    }
}
