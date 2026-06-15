<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Seeders;

use CodeIgniter\Database\Seeder;

class ProvisioningModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('modules')->where('key', 'provisioning')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "ProvisioningModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('modules')->insert([
                'key'         => 'provisioning',
                'name'        => 'Aprovisionamiento',
                'description' => 'Orquestador del ciclo de vida de identidades hacia GLPI, Mailcow e Intranet.',
                'route_base'  => 'aprovisionamiento',
                'icon'        => 'shield-check',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "ProvisioningModuleSeeder: module created (id={$moduleId}).\n";
        }

        $superAdmin = $this->db->table('roles')->where('name', 'SuperAdmin')->get()->getRow();
        if ($superAdmin) {
            $link = $this->db->table('role_module')
                ->where('role_id', $superAdmin->id)
                ->where('module_id', $moduleId)
                ->get()->getRow();

            if (! $link) {
                $this->db->table('role_module')->insert([
                    'role_id'   => $superAdmin->id,
                    'module_id' => $moduleId,
                ]);
                echo "ProvisioningModuleSeeder: granted access to SuperAdmin.\n";
            }
        }
    }
}
