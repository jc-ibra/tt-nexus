<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Service calendar for the SLA clock.
 *
 * Until now every SLA counted plain wall-clock minutes, so a mail that arrived
 * on Friday evening burned its whole budget over the weekend and every thread
 * showed up breached on Monday. This adds the two pieces the clock was missing:
 *
 *  - `business_hours_schedule`: one open/close window per weekday (ISO-8601
 *    numbering, 1 = Monday … 7 = Sunday), stored as JSON in the settings table.
 *  - `maildispatch_business_exceptions`: dated overrides for holidays and
 *    one-off closures / reduced hours.
 *
 * `business_hours_enabled` ships off so existing instances keep counting exactly
 * as before until the SuperAdmin reviews the seeded schedule and turns it on.
 */
class CreateMailDispatchBusinessHours extends Migration
{
    /** Seed: Mon-Fri 09:00-19:00, Sat 09:00-15:00, Sun closed. */
    private const DEFAULT_SCHEDULE = [
        '1' => ['closed' => false, 'open' => '09:00', 'close' => '19:00'],
        '2' => ['closed' => false, 'open' => '09:00', 'close' => '19:00'],
        '3' => ['closed' => false, 'open' => '09:00', 'close' => '19:00'],
        '4' => ['closed' => false, 'open' => '09:00', 'close' => '19:00'],
        '5' => ['closed' => false, 'open' => '09:00', 'close' => '19:00'],
        '6' => ['closed' => false, 'open' => '09:00', 'close' => '15:00'],
        '7' => ['closed' => true,  'open' => '09:00', 'close' => '15:00'],
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ([
            'business_hours_enabled'  => '0',
            'business_hours_schedule' => json_encode(self::DEFAULT_SCHEDULE, JSON_UNESCAPED_UNICODE),
        ] as $key => $value) {
            $exists = $this->db->table('maildispatch_settings')->where('key', $key)->countAllResults();
            if (! $exists) {
                $this->db->table('maildispatch_settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($this->db->tableExists('maildispatch_business_exceptions')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // One row per calendar date; the date is the natural key.
            'exception_date' => ['type' => 'DATE'],
            // Closed all day, or open with the reduced window below.
            'is_closed'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'open_time'      => ['type' => 'TIME', 'null' => true],
            'close_time'     => ['type' => 'TIME', 'null' => true],
            'note'           => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('exception_date');
        $this->forge->createTable('maildispatch_business_exceptions');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_business_exceptions', true);
        $this->db->table('maildispatch_settings')
            ->whereIn('key', ['business_hours_enabled', 'business_hours_schedule'])
            ->delete();
    }
}
