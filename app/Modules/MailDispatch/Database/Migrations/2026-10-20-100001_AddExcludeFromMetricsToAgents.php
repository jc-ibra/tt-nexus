<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a person (typically the supervisor) take conversations without their
 * work weighing on the team metrics: closures, response-time averages,
 * dispositions, the per-agent table and the highlights all skip them. Received
 * volume and backlog are untouched, that mail did reach the desk.
 */
class AddExcludeFromMetricsToAgents extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('exclude_from_metrics', 'maildispatch_agents')) {
            return;
        }

        $this->forge->addColumn('maildispatch_agents', [
            'exclude_from_metrics' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'is_active',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('exclude_from_metrics', 'maildispatch_agents')) {
            $this->forge->dropColumn('maildispatch_agents', 'exclude_from_metrics');
        }
    }
}
