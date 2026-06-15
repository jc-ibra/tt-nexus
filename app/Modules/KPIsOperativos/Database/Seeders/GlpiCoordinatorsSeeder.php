<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Seeders;

use CodeIgniter\Database\Seeder;

class GlpiCoordinatorsSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            ['zone' => 'DTN - ZONA 1', 'coord_name' => 'Emmanuel Ocampo', 'gte_name' => 'Luis E. Hernández'],
            ['zone' => 'DTN - ZONA 2', 'coord_name' => 'Alejo Vaquero',   'gte_name' => 'Luis E. Hernández'],
            ['zone' => 'DTN - ZONA 3', 'coord_name' => 'Itzel Espinoza',  'gte_name' => 'Luis E. Hernández'],
            ['zone' => 'DTS - ZONA 1', 'coord_name' => 'Jesús Chulín',    'gte_name' => 'Sócrates Hernández'],
            ['zone' => 'DTS - ZONA 2', 'coord_name' => 'Jorge González',  'gte_name' => 'Sócrates Hernández'],
            ['zone' => 'DTS - ZONA 3', 'coord_name' => 'Erick Sandoval',  'gte_name' => 'Sócrates Hernández'],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->table('kpi_glpi_coordinators')->where('zone', $row['zone'])->get()->getRow();
            if ($exists) {
                continue;
            }

            $this->db->table('kpi_glpi_coordinators')->insert([
                'zone'       => $row['zone'],
                'coord_name' => $row['coord_name'],
                'gte_name'   => $row['gte_name'],
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "GlpiCoordinatorsSeeder: 6 zones (DTN/DTS 1-3) ready.\n";
    }
}
