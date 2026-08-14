<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens maildispatch_events.type so the audit log can store every event the
 * module actually writes.
 *
 * The column was created with the seven types of the original state machine and
 * never grew, but later features log types outside that list: 'autoclose'
 * (auto-triage rule), 'autogen' (auto-created GLPI ticket) and 'verify' (agent
 * sign-off). Depending on the server's sql_mode those inserts either raise
 * "Data truncated for column 'type'" or land with an empty type, which is why
 * rows with type = '' exist. This adds them, plus 'forward' for the new
 * "reenviar" action.
 *
 * Appending values to the end of an ENUM keeps every existing value at the same
 * ordinal, so MySQL applies it in place (ALGORITHM=INSTANT) without rewriting
 * the table. Existing rows are untouched — including the ones already stored
 * with an empty type, whose original meaning is not recoverable.
 */
class AddEventTypesToMailDispatchEvents extends Migration
{
    /** The full set the column must accept, in order (existing ones first). */
    private const TYPES = [
        'assign', 'reassign', 'unassign', 'status', 'close', 'reopen', 'note',
        'autoclose', 'autogen', 'verify', 'forward',
    ];

    private const ORIGINAL = ['assign', 'reassign', 'unassign', 'status', 'close', 'reopen', 'note'];

    public function up(): void
    {
        $this->setEnum(self::TYPES);
    }

    public function down(): void
    {
        // Rows carrying one of the new types would not fit the old definition;
        // blank them first so the narrowing cannot fail or silently truncate.
        $this->db->table('maildispatch_events')
            ->whereNotIn('type', self::ORIGINAL)
            ->update(['type' => 'note']);

        $this->setEnum(self::ORIGINAL);
    }

    private function setEnum(array $types): void
    {
        $list = implode(',', array_map(fn (string $t): string => $this->db->escape($t), $types));

        $this->db->query("ALTER TABLE `maildispatch_events` MODIFY `type` ENUM({$list}) NOT NULL");
    }
}
