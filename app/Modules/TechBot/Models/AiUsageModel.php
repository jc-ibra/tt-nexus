<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Models;

use CodeIgniter\Model;

/**
 * Usage log for TechBot's Claude formatting. One row per formatting call so the
 * panel can show spend over a window.
 */
class AiUsageModel extends Model
{
    protected $table         = 'techbot_ai_usage';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'employee_id',
        'kind',
        'model',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'accepted',
        'created_at',
    ];

    /** Approx. USD per 1M tokens (input, output). Mirrors the ServiceDesk map. */
    private const PRICES = [
        'claude-haiku-4-5' => ['in' => 1.0, 'out' => 5.0],
        'claude-sonnet-5'  => ['in' => 3.0, 'out' => 15.0],
        'claude-opus-4-8'  => ['in' => 5.0, 'out' => 25.0],
    ];

    /**
     * Records one formatting call and returns its id (so `accepted` can be set
     * later once the technician chooses which text to keep).
     */
    public function record(?int $employeeId, string $model, int $inputTokens, int $outputTokens): int
    {
        return (int) $this->insert([
            'employee_id'    => $employeeId,
            'kind'           => 'format',
            'model'          => $model,
            'input_tokens'   => $inputTokens,
            'output_tokens'  => $outputTokens,
            'estimated_cost' => $this->estimateCost($model, $inputTokens, $outputTokens),
            'accepted'       => 0,
            'created_at'     => date('Y-m-d H:i:s'),
        ], true);
    }

    public function markAccepted(int $id): void
    {
        $this->update($id, ['accepted' => 1]);
    }

    /**
     * Aggregated stats for the panel card.
     *
     * @return array{calls:int,input:int,output:int,cost:float,accepted:int}
     */
    public function summary(int $days = 30): array
    {
        $row = $this->select(
            'COUNT(*) AS calls, '
            . 'COALESCE(SUM(input_tokens),0) AS input, '
            . 'COALESCE(SUM(output_tokens),0) AS output, '
            . 'COALESCE(SUM(estimated_cost),0) AS cost, '
            . 'COALESCE(SUM(accepted),0) AS accepted'
        )
            ->where('created_at >=', date('Y-m-d 00:00:00', strtotime("-{$days} days")))
            ->get()->getRowArray();

        return [
            'calls'    => (int) ($row['calls'] ?? 0),
            'input'    => (int) ($row['input'] ?? 0),
            'output'   => (int) ($row['output'] ?? 0),
            'cost'     => (float) ($row['cost'] ?? 0),
            'accepted' => (int) ($row['accepted'] ?? 0),
        ];
    }

    private function estimateCost(string $model, int $in, int $out): float
    {
        $p = self::PRICES[$model] ?? ['in' => 1.0, 'out' => 5.0];
        return round(($in / 1_000_000) * $p['in'] + ($out / 1_000_000) * $p['out'], 6);
    }
}
