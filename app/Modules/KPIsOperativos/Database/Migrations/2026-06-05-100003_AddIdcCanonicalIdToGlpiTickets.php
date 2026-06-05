<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIdcCanonicalIdToGlpiTickets extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('glpi_tickets', [
            'idc_canonical_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
                'after'    => 'idc',
            ],
        ]);

        $this->db->query('CREATE INDEX glpi_tickets_idc_canonical_id ON glpi_tickets (report_id, idc_canonical_id)');
        $this->db->query('
            ALTER TABLE glpi_tickets
            ADD CONSTRAINT fk_glpi_tickets_idc_canonical
            FOREIGN KEY (idc_canonical_id) REFERENCES glpi_idc_canonical(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE glpi_tickets DROP FOREIGN KEY fk_glpi_tickets_idc_canonical');
        $this->db->query('DROP INDEX glpi_tickets_idc_canonical_id ON glpi_tickets');
        $this->forge->dropColumn('glpi_tickets', 'idc_canonical_id');
    }
}
