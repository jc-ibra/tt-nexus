<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Models;

use CodeIgniter\Model;

/**
 * Usage log for the AI ticket creator. Records every Claude call so the admin
 * can track token spend and enforce a daily budget.
 */
class ServiceDeskAiUsageModel extends Model
{
    protected $table         = 'servicedesk_ai_usage';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'user_id',
        'kind',
        'model',
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
        'tickets_proposed',
        'tickets_created',
        'estimated_cost',
        'created_at',
    ];

    /**
     * Total tokens (input + output) consumed today across all users. Used to
     * enforce the daily budget before making a new call.
     */
    public function tokensToday(): int
    {
        $row = $this->select('COALESCE(SUM(input_tokens + output_tokens), 0) AS t')
            ->where('created_at >=', date('Y-m-d 00:00:00'))
            ->get()->getRowArray();

        return (int) ($row['t'] ?? 0);
    }

    /**
     * Aggregated stats for the admin dashboard.
     *
     * @return array{calls:int,input:int,output:int,cost:float,proposed:int,created:int}
     */
    public function summary(int $days = 30): array
    {
        $row = $this->select(
            'COUNT(*) AS calls, '
            . 'COALESCE(SUM(input_tokens),0) AS input, '
            . 'COALESCE(SUM(output_tokens),0) AS output, '
            . 'COALESCE(SUM(estimated_cost),0) AS cost, '
            . 'COALESCE(SUM(tickets_proposed),0) AS proposed, '
            . 'COALESCE(SUM(tickets_created),0) AS created'
        )
            ->where('created_at >=', date('Y-m-d 00:00:00', strtotime("-{$days} days")))
            ->get()->getRowArray();

        return [
            'calls'    => (int) ($row['calls'] ?? 0),
            'input'    => (int) ($row['input'] ?? 0),
            'output'   => (int) ($row['output'] ?? 0),
            'cost'     => (float) ($row['cost'] ?? 0),
            'proposed' => (int) ($row['proposed'] ?? 0),
            'created'  => (int) ($row['created'] ?? 0),
        ];
    }

    public function recent(int $limit = 50): array
    {
        return $this
            ->select('servicedesk_ai_usage.*, core_users.name AS user_name')
            ->join('core_users', 'core_users.id = servicedesk_ai_usage.user_id', 'left')
            ->orderBy('servicedesk_ai_usage.id', 'DESC')
            ->findAll($limit);
    }
}
