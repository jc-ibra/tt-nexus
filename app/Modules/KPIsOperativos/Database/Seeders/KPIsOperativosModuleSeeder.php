<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Database\Seeders;

use CodeIgniter\Database\Seeder;

class KPIsOperativosModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // Idempotent: skip if module key already exists
        $existing = $this->db->table('core_modules')->where('key', 'kpis_operativos')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "KPIsOperativosModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'kpis_operativos',
                'name'        => 'KPIs Operativos',
                'description' => 'Indicadores operativos por fuente (GLPI Tickets, futuras).',
                'route_base'  => 'kpi',
                'icon'        => 'chart-bar',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "KPIsOperativosModuleSeeder: module created (id={$moduleId}).\n";
        }

        // Grant access to SuperAdmin role if present
        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $link = $this->db->table('core_role_modules')
                ->where('role_id', $superAdmin->id)
                ->where('module_id', $moduleId)
                ->get()->getRow();

            if (! $link) {
                $this->db->table('core_role_modules')->insert([
                    'role_id'   => $superAdmin->id,
                    'module_id' => $moduleId,
                ]);
                echo "KPIsOperativosModuleSeeder: granted access to SuperAdmin.\n";
            }
        }
    }
}
