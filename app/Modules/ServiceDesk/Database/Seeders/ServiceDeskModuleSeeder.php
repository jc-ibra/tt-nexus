<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Registers the Service Desk module and wires access.
 *
 * - Creates the module row in core_modules.
 * - Creates a dedicated "ServiceDesk" role (operators who run bulk imports).
 * - Grants module access to both SuperAdmin (config + use) and ServiceDesk (use).
 *
 * Idempotent: safe to run repeatedly.
 */
class ServiceDeskModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ----------------------------------------------------------------
        // Module registry
        // ----------------------------------------------------------------
        $existing = $this->db->table('core_modules')->where('key', 'servicedesk')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "ServiceDeskModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'servicedesk',
                'name'        => 'Service Desk',
                'description' => 'Inserción masiva de tickets a GLPI con campos adicionales del plugin.',
                'route_base'  => 'servicedesk',
                'icon'        => 'headset',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "ServiceDeskModuleSeeder: module created (id={$moduleId}).\n";
        }

        // ----------------------------------------------------------------
        // Dedicated ServiceDesk role
        // ----------------------------------------------------------------
        $sdRole = $this->db->table('core_roles')->where('name', 'ServiceDesk')->get()->getRow();
        if (! $sdRole) {
            $this->db->table('core_roles')->insert([
                'name'        => 'ServiceDesk',
                'description' => 'Operadores de Service Desk: ejecutan la importación masiva de tickets.',
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $sdRoleId = (int) $this->db->insertID();
            echo "ServiceDeskModuleSeeder: ServiceDesk role created (id={$sdRoleId}).\n";
        } else {
            $sdRoleId = (int) $sdRole->id;
            echo "ServiceDeskModuleSeeder: ServiceDesk role already exists — skipped.\n";
        }

        // ----------------------------------------------------------------
        // Grant access: SuperAdmin (config + use) and ServiceDesk (use)
        // ----------------------------------------------------------------
        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();

        $roleIds = array_filter([
            $superAdmin ? (int) $superAdmin->id : null,
            $sdRoleId,
        ]);

        foreach ($roleIds as $roleId) {
            $link = $this->db->table('core_role_modules')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->get()->getRow();

            if (! $link) {
                $this->db->table('core_role_modules')->insert([
                    'role_id'   => $roleId,
                    'module_id' => $moduleId,
                ]);
                echo "ServiceDeskModuleSeeder: granted module access to role id={$roleId}.\n";
            }
        }
    }
}
