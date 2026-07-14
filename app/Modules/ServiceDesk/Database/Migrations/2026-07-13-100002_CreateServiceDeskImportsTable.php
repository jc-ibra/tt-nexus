<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bulk-import jobs. One row per uploaded Excel. The web upload validates and
 * enqueues (status=pending); the servicedesk:process-imports command picks it
 * up (status=processing), creates tickets in batches, and finalizes
 * (status=ready|failed). Progress counters are updated as it runs so the UI
 * can poll them.
 */
class CreateServiceDeskImportsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'source_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'source_path'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'output_path'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'log_path'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['pending', 'processing', 'ready', 'failed'], 'default' => 'pending'],
            'total_rows'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'processed_rows'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'succeeded_rows'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'failed_rows'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'error_message'   => ['type' => 'TEXT', 'null' => true],
            'uploaded_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'started_at'      => ['type' => 'DATETIME', 'null' => true],
            'finished_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('uploaded_by');
        $this->forge->addForeignKey('uploaded_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('servicedesk_imports');
    }

    public function down(): void
    {
        $this->forge->dropTable('servicedesk_imports', true);
    }
}
