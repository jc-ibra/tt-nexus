<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Seeders;

use CodeIgniter\Database\Seeder;

/**
 * Registers the MailDispatch module and wires access.
 *
 * - Creates the module row in core_modules (key `mail_dispatch`).
 * - Creates a dedicated "MailDispatch" role (agents who work the queue).
 * - Grants module access to both SuperAdmin (config + use) and MailDispatch (use).
 *
 * Idempotent: safe to run repeatedly.
 */
class MailDispatchModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ----------------------------------------------------------------
        // Module registry
        // ----------------------------------------------------------------
        $existing = $this->db->table('core_modules')->where('key', 'mail_dispatch')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "MailDispatchModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('core_modules')->insert([
                'key'         => 'mail_dispatch',
                'name'        => 'Despacho de Correo',
                'description' => 'Despacho del buzón compartido: cola de conversaciones, asignación y seguimiento.',
                'route_base'  => 'dispatch',
                'icon'        => 'inbox',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "MailDispatchModuleSeeder: module created (id={$moduleId}).\n";
        }

        // ----------------------------------------------------------------
        // Dedicated MailDispatch role
        // ----------------------------------------------------------------
        $role = $this->db->table('core_roles')->where('name', 'MailDispatch')->get()->getRow();
        if (! $role) {
            $this->db->table('core_roles')->insert([
                'name'        => 'MailDispatch',
                'description' => 'Agentes de despacho: atienden la cola del buzón compartido.',
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $roleId = (int) $this->db->insertID();
            echo "MailDispatchModuleSeeder: MailDispatch role created (id={$roleId}).\n";
        } else {
            $roleId = (int) $role->id;
            echo "MailDispatchModuleSeeder: MailDispatch role already exists — skipped.\n";
        }

        // ----------------------------------------------------------------
        // Grant access: SuperAdmin (config + use) and MailDispatch (use)
        // ----------------------------------------------------------------
        $superAdmin = $this->db->table('core_roles')->where('name', 'SuperAdmin')->get()->getRow();

        $roleIds = array_filter([
            $superAdmin ? (int) $superAdmin->id : null,
            $roleId,
        ]);

        foreach ($roleIds as $rid) {
            $link = $this->db->table('core_role_modules')
                ->where('role_id', $rid)
                ->where('module_id', $moduleId)
                ->get()->getRow();

            if (! $link) {
                $this->db->table('core_role_modules')->insert([
                    'role_id'   => $rid,
                    'module_id' => $moduleId,
                ]);
                echo "MailDispatchModuleSeeder: granted module access to role id={$rid}.\n";
            }
        }
    }
}
