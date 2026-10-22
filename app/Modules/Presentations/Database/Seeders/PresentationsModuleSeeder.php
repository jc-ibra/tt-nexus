<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Database\Seeders;

use CodeIgniter\Database\Seeder;

class PresentationsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('core_modules')->where('key', 'presentations')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "PresentationsModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'presentations',
                'name'        => 'Presentaciones',
                'description' => 'Presentación mensual de Operaciones: se reemplaza el HTML y todos los del módulo lo visualizan.',
                'route_base'  => 'presentations',
                'icon'        => 'monitor',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "PresentationsModuleSeeder: module created (id={$moduleId}).\n";
        }

        $perm = $this->db->table('core_permissions')
            ->where('module_id', $moduleId)->where('key', 'presentations.upload')->get()->getRow();
        if ($perm) {
            $permId = (int) $perm->id;
        } else {
            $this->db->table('core_permissions')->insert([
                'module_id'  => $moduleId,
                'key'        => 'presentations.upload',
                'name'       => 'Reemplazar la presentación',
                'created_at' => $now,
            ]);
            $permId = (int) $this->db->insertID();
            echo "PresentationsModuleSeeder: permission created.\n";
        }

        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $link = $this->db->table('core_role_modules')
                ->where('role_id', $superAdmin->id)->where('module_id', $moduleId)->get()->getRow();
            if (! $link) {
                $this->db->table('core_role_modules')->insert([
                    'role_id' => $superAdmin->id, 'module_id' => $moduleId,
                ]);
                echo "PresentationsModuleSeeder: granted access to SuperAdmin.\n";
            }

        }

        // Every role with module access can also replace the presentation.
        $granted = 0;
        $roleIds = array_column(
            $this->db->table('core_role_modules')->select('role_id')->where('module_id', $moduleId)->get()->getResultArray(),
            'role_id'
        );
        foreach ($roleIds as $roleId) {
            $pl = $this->db->table('core_role_permissions')
                ->where('role_id', $roleId)->where('permission_id', $permId)->get()->getRow();
            if (! $pl) {
                $this->db->table('core_role_permissions')->insert([
                    'role_id' => $roleId, 'permission_id' => $permId,
                ]);
                $granted++;
            }
        }
        echo "PresentationsModuleSeeder: upload permission granted to {$granted} new role(s).\n";
    }
}
