<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Registers the AgentKpis module and grants access to SuperAdmin. Idempotent.
 */
class AgentKpisModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('core_modules')->where('key', 'agent_kpis')->get()->getRow();
        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "AgentKpisModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'agent_kpis',
                'name'        => 'Evaluación de Agentes',
                'description' => 'Evaluación mensual de los agentes N1: KPIs cuantitativos (de las auditorías) + rúbrica cualitativa.',
                'route_base'  => 'agent-kpis',
                'icon'        => 'award',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "AgentKpisModuleSeeder: module created (id={$moduleId}).\n";
        }

        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $roleId = (int) $superAdmin->id;
            $link = $this->db->table('core_role_modules')
                ->where('role_id', $roleId)->where('module_id', $moduleId)->get()->getRow();
            if (! $link) {
                $this->db->table('core_role_modules')->insert(['role_id' => $roleId, 'module_id' => $moduleId]);
                echo "AgentKpisModuleSeeder: granted access to SuperAdmin.\n";
            } else {
                echo "AgentKpisModuleSeeder: SuperAdmin already had access — skipped.\n";
            }
        }
    }
}
