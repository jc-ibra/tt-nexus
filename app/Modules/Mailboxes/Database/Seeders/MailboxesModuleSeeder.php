<?php

declare(strict_types=1);

namespace App\Modules\Mailboxes\Database\Seeders;

use CodeIgniter\Database\Seeder;

class MailboxesModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('modules')->where('key', 'mailboxes')->get()->getRow();

        if ($existing) {
            $moduleId = (int) $existing->id;
            echo "MailboxesModuleSeeder: module already registered (id={$moduleId}).\n";
        } else {
            $this->db->table('modules')->insert([
                'key'         => 'mailboxes',
                'name'        => 'Buzones',
                'description' => 'Administración de buzones de correo vía API Mailcow.',
                'route_base'  => 'mailboxes',
                'icon'        => 'inbox',
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $moduleId = (int) $this->db->insertID();
            echo "MailboxesModuleSeeder: module created (id={$moduleId}).\n";
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
                echo "MailboxesModuleSeeder: granted access to SuperAdmin.\n";
            }
        }
    }
}
