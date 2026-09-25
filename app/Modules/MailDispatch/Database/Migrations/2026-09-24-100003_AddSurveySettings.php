<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default CSAT survey settings into maildispatch_settings (key-value).
 * Idempotent: checks existence before inserting, mirrors AddSyncSinceSetting.
 */
class AddSurveySettings extends Migration
{
    /** key => default value. Copy is Spanish (UI text), keys/values otherwise plain. */
    private const DEFAULTS = [
        'survey_enabled'                => '0',
        'survey_ttl_days'               => '7',
        'survey_block_title'            => '¿Cómo estuvo nuestra atención?',
        'survey_block_text'             => 'Tu opinión nos ayuda a mejorar el servicio. Toma menos de un minuto.',
        'survey_block_cta'              => 'Calificar la atención',
        'survey_rate_limit_per_hour'    => '20',
        'survey_reissue_after_response' => '0',
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (self::DEFAULTS as $key => $value) {
            $exists = $this->db->table('maildispatch_settings')->where('key', $key)->countAllResults();
            if (! $exists) {
                $this->db->table('maildispatch_settings')->insert([
                    'key' => $key, 'value' => $value, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('maildispatch_settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
}
