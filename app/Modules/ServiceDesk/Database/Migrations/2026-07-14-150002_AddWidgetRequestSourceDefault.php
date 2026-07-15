<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default for the widget's GLPI request source (requesttypes_id) into
 * servicedesk_settings. Idempotent: only inserts the key when missing, so
 * re-running never clobbers an admin's saved value.
 *
 * The ServiceDeskSettings accessor already falls back to '0' when the row is
 * absent; this migration only makes the row visible in the admin form.
 */
class AddWidgetRequestSourceDefault extends Migration
{
    private string $key     = 'widget_request_source_id';
    private string $default = '0';

    public function up(): void
    {
        $exists = $this->db->table('servicedesk_settings')
            ->where('key', $this->key)
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('servicedesk_settings')->insert([
                'key'        => $this->key,
                'value'      => $this->default,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('servicedesk_settings')
            ->where('key', $this->key)
            ->delete();
    }
}
