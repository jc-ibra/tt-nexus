<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Registers the Reports module and grants access to SuperAdmin.
 * Idempotent: safe to run repeatedly.
 */
class ReportsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('core_modules')->where('key', 'reports')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "ReportsModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'reports',
                'name'        => 'Informes',
                'description' => 'Informe ejecutivo mensual: mesa de ayuda, correo, calidad y desempeño de agentes.',
                'route_base'  => 'reports',
                'icon'        => 'presentation-chart',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "ReportsModuleSeeder: module created (id={$moduleId}).\n";
        }

        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if (! $superAdmin) {
            echo "ReportsModuleSeeder: SuperAdmin role not found — skipped access grant.\n";
            return;
        }

        $link = $this->db->table('core_role_modules')
            ->where('role_id', $superAdmin->id)
            ->where('module_id', $moduleId)
            ->get()->getRow();

        if (! $link) {
            $this->db->table('core_role_modules')->insert([
                'role_id'   => $superAdmin->id,
                'module_id' => $moduleId,
            ]);
            echo "ReportsModuleSeeder: granted module access to SuperAdmin.\n";
        } else {
            echo "ReportsModuleSeeder: SuperAdmin already has access — skipped.\n";
        }
    }
}
