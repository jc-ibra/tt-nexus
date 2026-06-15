<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use CodeIgniter\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ----------------------------------------------------------------
        // Roles
        // ----------------------------------------------------------------
        $existingRole = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();
        if (! $existingRole) {
            $this->db->table('core_roles')->insert([
                'name'        => 'SuperAdmin',
                'description' => 'Acceso completo a todos los módulos',
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $superAdminRoleId = $this->db->insertID();
            echo "CoreSeeder: SuperAdmin role created.\n";
        } else {
            $superAdminRoleId = $existingRole->id;
            echo "CoreSeeder: SuperAdmin role already exists — skipped.\n";
        }

        // ----------------------------------------------------------------
        // Admin user
        // ----------------------------------------------------------------
        $email    = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name     = env('ADMIN_NAME', 'Administrator');

        if (empty($email) || empty($password)) {
            throw new \RuntimeException(
                'CoreSeeder requires ADMIN_EMAIL and ADMIN_PASSWORD to be defined in .env — no defaults are provided.'
            );
        }

        $existingUser = $this->db->table('core_users')->where('email', $email)->get()->getRow();
        if (! $existingUser) {
            $this->db->table('core_users')->insert([
                'name'       => $name,
                'email'      => $email,
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adminUserId = $this->db->insertID();

            $this->db->table('core_user_roles')->insert([
                'user_id' => $adminUserId,
                'role_id' => $superAdminRoleId,
            ]);
            echo "CoreSeeder: Admin user ({$email}) created.\n";
        } else {
            echo "CoreSeeder: Admin user ({$email}) already exists — skipped.\n";
        }

        // ----------------------------------------------------------------
        // Modules registry
        // ----------------------------------------------------------------
        $existingModule = $this->db->table('core_modules')->where('key', 'communications')->get()->getRow();
        if (! $existingModule) {
            $this->db->table('core_modules')->insert([
                'key'         => 'communications',
                'name'        => 'Comunicaciones',
                'description' => 'Envío masivo de comunicados internos por correo',
                'route_base'  => 'comms',
                'icon'        => 'mail',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $commsModuleId = $this->db->insertID();
            echo "CoreSeeder: Communications module created.\n";
        } else {
            $commsModuleId = $existingModule->id;
            echo "CoreSeeder: Communications module already exists — skipped.\n";
        }

        // ----------------------------------------------------------------
        // Grant SuperAdmin access to Communications module
        // ----------------------------------------------------------------
        $existingLink = $this->db->table('core_role_modules')
            ->where('role_id', $superAdminRoleId)
            ->where('module_id', $commsModuleId)
            ->get()->getRow();

        if (! $existingLink) {
            $this->db->table('core_role_modules')->insert([
                'role_id'   => $superAdminRoleId,
                'module_id' => $commsModuleId,
            ]);
            echo "CoreSeeder: SuperAdmin granted access to Communications.\n";
        }
    }
}
