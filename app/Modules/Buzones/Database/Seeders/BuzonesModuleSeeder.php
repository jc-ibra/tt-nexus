<?php

declare(strict_types=1);

namespace App\Modules\Buzones\Database\Seeders;

use CodeIgniter\Database\Seeder;

class BuzonesModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('modules')->where('key', 'buzones')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "BuzonesModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('modules')->insert([
                'key'         => 'buzones',
                'name'        => 'Buzones',
                'description' => 'Administración de buzones de correo vía API Mailcow.',
                'route_base'  => 'buzones',
                'icon'        => 'inbox',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "BuzonesModuleSeeder: module created (id={$moduleId}).\n";
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
                echo "BuzonesModuleSeeder: granted access to SuperAdmin.\n";
            }
        }
    }
}
