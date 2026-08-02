<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Models;

use CodeIgniter\Model;

class KpiSnapshotModel extends Model
{
    protected $table         = 'agent_kpis_kpi_snapshots';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'evaluation_id', 'kpi_number', 'total_tickets_evaluated', 'tickets_meeting_criteria',
        'calculated_value', 'threshold_met', 'detail_json', 'created_at',
    ];

    public function forEvaluation(int $evaluationId): array
    {
        return $this->where('evaluation_id', $evaluationId)->orderBy('kpi_number', 'ASC')->findAll();
    }

    public function replaceForEvaluation(int $evaluationId, array $rows): void
    {
        $this->where('evaluation_id', $evaluationId)->delete();
        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
