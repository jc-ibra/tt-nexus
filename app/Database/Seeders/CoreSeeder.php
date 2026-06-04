<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use CodeIgniter\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------------------------------------------
        // Roles
        // ----------------------------------------------------------------
        $roleId = $this->db->table('roles')->insert([
            'name'        => 'SuperAdmin',
            'description' => 'Acceso completo a todos los módulos',
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $superAdminRoleId = $this->db->insertID();

        // ----------------------------------------------------------------
        // Admin user
        // ----------------------------------------------------------------
        $name     = env('ADMIN_NAME', 'Administrator');
        $email    = env('ADMIN_EMAIL', 'admin@ibrastudio.com');
        $password = env('ADMIN_PASSWORD', 'changeme123');

        $this->db->table('users')->insert([
            'name'       => $name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $adminUserId = $this->db->insertID();

        $this->db->table('user_roles')->insert([
            'user_id' => $adminUserId,
            'role_id' => $superAdminRoleId,
        ]);

        // ----------------------------------------------------------------
        // Modules registry
        // ----------------------------------------------------------------
        $this->db->table('modules')->insert([
            'key'         => 'communications',
            'name'        => 'Comunicaciones',
            'description' => 'Envío masivo de comunicados internos por correo',
            'route_base'  => 'comms',
            'icon'        => 'mail',
            'is_active'   => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $commsModuleId = $this->db->insertID();

        // ----------------------------------------------------------------
        // Grant SuperAdmin access to all modules
        // ----------------------------------------------------------------
        $this->db->table('role_module')->insert([
            'role_id'   => $superAdminRoleId,
            'module_id' => $commsModuleId,
        ]);

        echo "CoreSeeder: SuperAdmin role, admin user ({$email}), and Communications module created.\n";
    }
}
