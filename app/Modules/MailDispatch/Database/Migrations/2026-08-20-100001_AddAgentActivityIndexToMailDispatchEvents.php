<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Index for "when did each agent last do anything".
 *
 * The team board reads MAX(created_at) GROUP BY user_id over the whole audit
 * log on every render (every 30 s, per dispatcher). The FK on user_id alone
 * only narrows the rows; adding created_at to the same key lets MySQL take the
 * max straight from the index instead of touching the table.
 */
class AddAgentActivityIndexToMailDispatchEvents extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex()) {
            return;
        }

        $this->db->query('CREATE INDEX `md_events_user_created` ON `maildispatch_events` (`user_id`, `created_at`)');
    }

    public function down(): void
    {
        if ($this->hasIndex()) {
            $this->db->query('DROP INDEX `md_events_user_created` ON `maildispatch_events`');
        }
    }

    private function hasIndex(): bool
    {
        foreach ($this->db->getIndexData('maildispatch_events') as $index) {
            if ($index->name === 'md_events_user_created') {
                return true;
            }
        }

        return false;
    }
}
