<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIdcCanonicalIdToGlpiTickets extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('kpi_glpi_tickets', [
            'idc_canonical_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
                'after'    => 'idc',
            ],
        ]);

        $this->db->query('CREATE INDEX kpi_glpi_tickets_idc_canonical_id ON kpi_glpi_tickets (report_id, idc_canonical_id)');
        $this->db->query('
            ALTER TABLE kpi_glpi_tickets
            ADD CONSTRAINT fk_kpi_glpi_tickets_idc_canonical
            FOREIGN KEY (idc_canonical_id) REFERENCES kpi_glpi_idc_canonical(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE kpi_glpi_tickets DROP FOREIGN KEY fk_kpi_glpi_tickets_idc_canonical');
        $this->db->query('DROP INDEX kpi_glpi_tickets_idc_canonical_id ON glpi_tickets');
        $this->forge->dropColumn('kpi_glpi_tickets', 'idc_canonical_id');
    }
}
