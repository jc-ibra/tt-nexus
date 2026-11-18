<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeDocumentTables extends Migration
{
    public function up(): void
    {
        // Catalog of document types RRHH can request (INE, contract, etc.).
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'key'           => ['type' => 'VARCHAR', 'constraint' => 64],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'allowed_mimes' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'application/pdf,image/jpeg,image/png'],
            'max_size_kb'   => ['type' => 'INT', 'unsigned' => true, 'default' => 5120],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'    => ['type' => 'INT', 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('key');
        $this->forge->createTable('employees_document_types', true);

        // One request instance for an employee + document type.
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'      => ['type' => 'INT', 'unsigned' => true],
            'document_type_id' => ['type' => 'INT', 'unsigned' => true],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'requested'],
            // requested | uploaded | under_review | accepted | rejected | cancelled
            'notes'            => ['type' => 'TEXT', 'null' => true],
            'rejection_reason' => ['type' => 'TEXT', 'null' => true],
            'due_at'           => ['type' => 'DATETIME', 'null' => true],
            'requested_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_at'      => ['type' => 'DATETIME', 'null' => true],
            'uploaded_at'      => ['type' => 'DATETIME', 'null' => true],
            'original_name'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'stored_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'size_bytes'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('status');
        $this->forge->createTable('employees_document_requests', true);

        // Seed a few default types.
        $now = date('Y-m-d H:i:s');
        $this->db->table('employees_document_types')->insertBatch([
            [
                'key' => 'ine', 'name' => 'INE / Identificación oficial',
                'description' => 'Identificación oficial vigente (ambos lados si aplica).',
                'allowed_mimes' => 'application/pdf,image/jpeg,image/png',
                'max_size_kb' => 5120, 'is_active' => 1, 'sort_order' => 10,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'comprobante_domicilio', 'name' => 'Comprobante de domicilio',
                'description' => 'No mayor a 3 meses.',
                'allowed_mimes' => 'application/pdf,image/jpeg,image/png',
                'max_size_kb' => 5120, 'is_active' => 1, 'sort_order' => 20,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'rfc_constancia', 'name' => 'Constancia de situación fiscal (RFC)',
                'description' => null,
                'allowed_mimes' => 'application/pdf,image/jpeg,image/png',
                'max_size_kb' => 5120, 'is_active' => 1, 'sort_order' => 30,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'contrato', 'name' => 'Contrato firmado',
                'description' => 'Contrato laboral firmado (PDF).',
                'allowed_mimes' => 'application/pdf',
                'max_size_kb' => 10240, 'is_active' => 1, 'sort_order' => 40,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('employees_document_requests', true);
        $this->forge->dropTable('employees_document_types', true);
    }
}
