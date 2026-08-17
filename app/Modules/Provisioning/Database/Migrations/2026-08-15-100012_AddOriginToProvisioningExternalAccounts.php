<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Distinguishes an account Nexus CREATED in the external system from one that
 * already existed there and was merely LINKED to the employee.
 *
 * It drives the UI badge ("Vinculada") and gates "Desvincular": unlinking is
 * only ever valid for a link, since deleting the row of an account Nexus itself
 * created would orphan a real user in the external system.
 */
class AddOriginToProvisioningExternalAccounts extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('origin', 'provisioning_external_accounts')) {
            return;
        }

        $this->forge->addColumn('provisioning_external_accounts', [
            'origin' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'created',
                'after'      => 'status',
                'comment'    => 'created = provisioned by Nexus, linked = pre-existing account mapped to the employee',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('origin', 'provisioning_external_accounts')) {
            $this->forge->dropColumn('provisioning_external_accounts', 'origin');
        }
    }
}
