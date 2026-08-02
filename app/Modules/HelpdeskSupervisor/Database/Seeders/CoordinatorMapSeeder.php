<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the state -> coordinator map (Manual Parte 3.5). The coordinator GLPI
 * user id is left null on purpose: it is resolved from the coordinator name
 * against glpi_users at audit time (same instance). Idempotent: existing rows
 * are updated, missing ones inserted.
 */
class CoordinatorMapSeeder extends Seeder
{
    /** [state, coordinator, zone] straight from the manual. */
    private const MAP = [
        ['Aguascalientes',      'Jorge González',   'DTS - Zona 2'],
        ['Baja California',     'Itzel Espinoza',   'DTN - Zona 3'],
        ['Baja California Sur', 'Itzel Espinoza',   'DTN - Zona 3'],
        ['Campeche',            'Erick Sandoval',   'DTS - Zona 3'],
        ['Chiapas',             'Erick Sandoval',   'DTS - Zona 3'],
        ['Chihuahua',           'Alejo Vaquero',    'DTN - Zona 2'],
        ['Ciudad de México',    'Erick Sandoval',   'DTS - Zona 3'],
        ['Coahuila',            'Alejo Vaquero',    'DTN - Zona 2'],
        ['Colima',              'Jorge González',   'DTS - Zona 2'],
        ['Durango',             'Alejo Vaquero',    'DTN - Zona 2'],
        ['Estado de México',    'Erick Sandoval',   'DTS - Zona 3'],
        ['Guanajuato',          'Jorge González',   'DTS - Zona 2'],
        ['Guerrero',            'Erick Sandoval',   'DTS - Zona 3'],
        ['Hidalgo',             'Erick Sandoval',   'DTS - Zona 3'],
        ['Jalisco',             'Jorge González',   'DTS - Zona 2'],
        ['Michoacán',           'Jorge González',   'DTS - Zona 2'],
        ['Morelos',             'Erick Sandoval',   'DTS - Zona 3'],
        ['Nayarit',             'Jorge González',   'DTS - Zona 2'],
        ['Nuevo León',          'Emmanuel Ocampo',  'DTN - Zona 1'],
        ['Oaxaca',              'Erick Sandoval',   'DTS - Zona 3'],
        ['Puebla',              'Erick Sandoval',   'DTS - Zona 3'],
        ['Querétaro',           'Erick Sandoval',   'DTS - Zona 3'],
        ['Quintana Roo',        'Erick Sandoval',   'DTS - Zona 3'],
        ['San Luis Potosí',     'Jorge González',   'DTS - Zona 2'],
        ['Sinaloa',             'Itzel Espinoza',   'DTN - Zona 3'],
        ['Sonora',              'Itzel Espinoza',   'DTN - Zona 3'],
        ['Tabasco',             'Erick Sandoval',   'DTS - Zona 3'],
        ['Tamaulipas',          'Jesús Chulín',     'DTS - Zona 1'],
        ['Tlaxcala',            'Erick Sandoval',   'DTS - Zona 3'],
        ['Veracruz',            'Erick Sandoval',   'DTS - Zona 3'],
        ['Yucatán',             'Erick Sandoval',   'DTS - Zona 3'],
        ['Zacatecas',           'Jesús Chulín',     'DTS - Zona 1'],
    ];

    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $table = $this->db->table('helpdesk_supervisor_coordinator_map');
        $insert = 0;
        $update = 0;

        foreach (self::MAP as [$state, $coordinator, $zone]) {
            $existing = $table->where('state_name', $state)->get()->getRow();
            if ($existing) {
                $table->where('id', $existing->id)->update([
                    'coordinator_name' => $coordinator,
                    'zone'             => $zone,
                    'updated_at'       => $now,
                ]);
                $update++;
            } else {
                $table->insert([
                    'state_name'               => $state,
                    'coordinator_glpi_user_id' => null,
                    'coordinator_name'         => $coordinator,
                    'zone'                     => $zone,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ]);
                $insert++;
            }
            // Rebuild the builder state for the next iteration.
            $table = $this->db->table('helpdesk_supervisor_coordinator_map');
        }

        echo "CoordinatorMapSeeder: {$insert} insertados, {$update} actualizados (" . count(self::MAP) . " estados).\n";
    }
}
