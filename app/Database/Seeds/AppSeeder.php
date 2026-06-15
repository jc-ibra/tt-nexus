<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AppSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CoreSeeder::class);

        $this->call(\App\Modules\Employees\Database\Seeders\EmployeesModuleSeeder::class);
        $this->call(\App\Modules\Employees\Database\Seeders\EmployeeAreasSeeder::class);
        $this->call(\App\Modules\Employees\Database\Seeders\EmployeeDepartmentsSeeder::class);
        $this->call(\App\Modules\Employees\Database\Seeders\EmployeePositionsSeeder::class);

        $this->call(\App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder::class);
        $this->call(\App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder::class);

        $this->call(\App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder::class);

        $this->call(\App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder::class);
    }
}
