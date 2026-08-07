<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds `smtp_use_core`: when '1' (default) IMAP-mode replies are sent through the
 * platform's Core SMTP (Configuración › SMTP) instead of a mailbox-specific SMTP,
 * so the admin does not have to duplicate credentials. Set to '0' to use the
 * module's own smtp_* fields.
 */
class AddSmtpUseCoreSetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('maildispatch_settings')->where('key', 'smtp_use_core')->countAllResults();
        if (! $exists) {
            $this->db->table('maildispatch_settings')->insert([
                'key'        => 'smtp_use_core',
                'value'      => '1',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('maildispatch_settings')->where('key', 'smtp_use_core')->delete();
    }
}
