<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Changes the CSAT survey from "one response per conversation, first one
 * closes it" to a shared quota: the same token accepts up to
 * `survey_max_responses` answers before it closes, because a support thread
 * usually has several copied recipients and only letting the first one
 * answer wastes real feedback.
 *
 * - maildispatch_survey_tokens gains `response_count` (how many answers this
 *   token has taken) and `last_responded_at`. `responded_at` is kept as-is —
 *   it already meant "first response", which stays true.
 * - maildispatch_survey_responses.token_id drops its UNIQUE constraint: more
 *   than one row per token is now legitimate. The new plain index is created
 *   BEFORE dropping the unique one: token_id carries a foreign key that today
 *   relies on that unique index to back it, and MySQL refuses to drop a
 *   unique index still backing a FK (errno 150) if nothing else covers it.
 *
 * down() is destructive by necessity: restoring UNIQUE(token_id) with several
 * rows per token requires discarding every row but the first of each token,
 * same trade-off already accepted by 2026-08-14-100001_AddEventTypesTo...
 * when it collapses unknown event types back to 'note'.
 */
class AddSurveyMultiResponse extends Migration
{
    private const NEW_INDEX = 'idx_md_survey_resp_token';

    public function up(): void
    {
        if (! $this->db->fieldExists('response_count', 'maildispatch_survey_tokens')) {
            $this->forge->addColumn('maildispatch_survey_tokens', [
                'response_count' => [
                    'type' => 'INT', 'constraint' => 11, 'unsigned' => true,
                    'default' => 0, 'after' => 'responded_at',
                ],
            ]);
        }
        if (! $this->db->fieldExists('last_responded_at', 'maildispatch_survey_tokens')) {
            $this->forge->addColumn('maildispatch_survey_tokens', [
                'last_responded_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'response_count'],
            ]);
        }

        // Defensive backfill from whatever responses already exist (the table
        // is empty in practice at the time this ships, but this keeps the
        // migration correct if it ever runs against real data).
        $this->db->query(
            'UPDATE maildispatch_survey_tokens t
               LEFT JOIN (SELECT token_id, COUNT(*) AS n, MAX(responded_at) AS last_at
                            FROM maildispatch_survey_responses GROUP BY token_id) r
                 ON r.token_id = t.id
                SET t.response_count    = COALESCE(r.n, 0),
                    t.last_responded_at = r.last_at'
        );

        // Plain index first (see class docblock), THEN drop the unique one.
        if (! $this->hasIndex(self::NEW_INDEX)) {
            $this->db->query('CREATE INDEX ' . self::NEW_INDEX . ' ON maildispatch_survey_responses (token_id)');
        }
        $unique = $this->uniqueTokenIndexName();
        if ($unique !== null) {
            $this->db->query('DROP INDEX `' . $unique . '` ON maildispatch_survey_responses');
        }
    }

    public function down(): void
    {
        // Keep only the earliest response per token so the unique index can
        // be restored. Every later response for that token is discarded.
        $this->db->query(
            'DELETE r FROM maildispatch_survey_responses r
               JOIN (SELECT token_id, MIN(id) AS keep_id
                       FROM maildispatch_survey_responses GROUP BY token_id) k
                 ON k.token_id = r.token_id
              WHERE r.id > k.keep_id'
        );

        if ($this->uniqueTokenIndexName() === null) {
            $this->db->query('CREATE UNIQUE INDEX `token_id` ON maildispatch_survey_responses (token_id)');
        }
        if ($this->hasIndex(self::NEW_INDEX)) {
            $this->db->query('DROP INDEX ' . self::NEW_INDEX . ' ON maildispatch_survey_responses');
        }

        foreach (['last_responded_at', 'response_count'] as $col) {
            if ($this->db->fieldExists($col, 'maildispatch_survey_tokens')) {
                $this->forge->dropColumn('maildispatch_survey_tokens', $col);
            }
        }
    }

    private function uniqueTokenIndexName(): ?string
    {
        foreach ($this->db->getIndexData('maildispatch_survey_responses') as $idx) {
            if ($idx->type === 'UNIQUE' && $idx->fields === ['token_id']) {
                return $idx->name;
            }
        }
        return null;
    }

    private function hasIndex(string $name): bool
    {
        foreach ($this->db->getIndexData('maildispatch_survey_responses') as $idx) {
            if ($idx->name === $name) {
                return true;
            }
        }
        return false;
    }
}
