<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Registers the HelpdeskSupervisor module and grants access to SuperAdmin.
 *
 * The settings rows are seeded by the migration; the coordinator map is seeded
 * separately (CoordinatorMapSeeder). Idempotent: safe to run repeatedly.
 */
class HelpdeskSupervisorModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('core_modules')->where('key', 'helpdesk_supervisor')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "HelpdeskSupervisorModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'helpdesk_supervisor',
                'name'        => 'Supervisor de Mesa',
                'description' => 'Audita los tickets de GLPI contra el Manual MAC y muestra las desviaciones por agente y por regla.',
                'route_base'  => 'helpdesk-supervisor',
                'icon'        => 'clipboard-check',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "HelpdeskSupervisorModuleSeeder: module created (id={$moduleId}).\n";
        }

        // Grant access to SuperAdmin (the supervisor operates as SuperAdmin for now).
        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $roleId = (int) $superAdmin->id;
            $link = $this->db->table('core_role_modules')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->get()->getRow();
            if (! $link) {
                $this->db->table('core_role_modules')->insert([
                    'role_id'   => $roleId,
                    'module_id' => $moduleId,
                ]);
                echo "HelpdeskSupervisorModuleSeeder: granted access to SuperAdmin.\n";
            } else {
                echo "HelpdeskSupervisorModuleSeeder: SuperAdmin already had access — skipped.\n";
            }
        }
    }
}
