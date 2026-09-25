<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seeds the default for the CSAT survey's shared response quota (see
 * 2026-09-25-100001_AddSurveyMultiResponse). Idempotent, mirrors
 * 2026-09-24-100003_AddSurveySettings.
 */
class AddSurveyMaxResponsesSetting extends Migration
{
    private const DEFAULTS = [
        'survey_max_responses' => '3',
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
