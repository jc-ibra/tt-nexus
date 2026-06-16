<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Seeders;

use CodeIgniter\Database\Seeder;

class MsLicensesSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $licenses = [
            ['code' => 'exchange_p1',    'name' => 'Exchange Online (Plan 1)',        'description' => 'Correo corporativo básico con buzón de 50 GB.'],
            ['code' => 'm365_biz_basic', 'name' => 'Microsoft 365 Empresa Básico',   'description' => 'Correo + Teams + apps web de Office 365.'],
            ['code' => 'm365_biz_std',   'name' => 'Microsoft 365 Empresa Estándar', 'description' => 'Todo lo de Básico más apps de escritorio de Office.'],
        ];

        foreach ($licenses as $lic) {
            $exists = $this->db->table('provisioning_ms_licenses')
                ->where('code', $lic['code'])
                ->countAllResults();

            if (! $exists) {
                $this->db->table('provisioning_ms_licenses')->insert([
                    'name'        => $lic['name'],
                    'code'        => $lic['code'],
                    'description' => $lic['description'],
                    'is_active'   => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                echo "MsLicensesSeeder: creada licencia '{$lic['code']}'.\n";
            } else {
                echo "MsLicensesSeeder: licencia '{$lic['code']}' ya existe.\n";
            }
        }
    }
}
