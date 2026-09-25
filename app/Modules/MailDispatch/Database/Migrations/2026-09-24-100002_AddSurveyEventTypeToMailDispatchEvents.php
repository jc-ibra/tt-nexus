<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds 'survey' to maildispatch_events.type so answering the CSAT survey can
 * be logged to the thread's bitácora (SurveyService::record()).
 *
 * Appending to the end of the ENUM keeps every existing value at the same
 * ordinal, so MySQL applies it in place (ALGORITHM=INSTANT), same as
 * 2026-08-14-100001_AddEventTypesToMailDispatchEvents.php.
 */
class AddSurveyEventTypeToMailDispatchEvents extends Migration
{
    private const TYPES = [
        'assign', 'reassign', 'unassign', 'status', 'close', 'reopen', 'note',
        'autoclose', 'autogen', 'verify', 'forward', 'survey',
    ];

    private const PREVIOUS = [
        'assign', 'reassign', 'unassign', 'status', 'close', 'reopen', 'note',
        'autoclose', 'autogen', 'verify', 'forward',
    ];

    public function up(): void
    {
        $this->setEnum(self::TYPES);
    }

    public function down(): void
    {
        // Rows carrying 'survey' would not fit the previous definition; blank
        // them to 'note' first so the narrowing cannot fail or truncate.
        $this->db->table('maildispatch_events')
            ->where('type', 'survey')
            ->update(['type' => 'note']);

        $this->setEnum(self::PREVIOUS);
    }

    private function setEnum(array $types): void
    {
        $list = implode(',', array_map(fn (string $t): string => $this->db->escape($t), $types));

        $this->db->query("ALTER TABLE `maildispatch_events` MODIFY `type` ENUM({$list}) NOT NULL");
    }
}
