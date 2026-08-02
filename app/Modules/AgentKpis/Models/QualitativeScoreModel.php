<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Models;

use CodeIgniter\Model;

class QualitativeScoreModel extends Model
{
    protected $table         = 'agent_kpis_qualitative_scores';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'evaluation_id', 'competency_key', 'competency_name', 'weight', 'score', 'evidence',
    ];

    /** @return array<string,array<string,mixed>> competency_key => row */
    public function forEvaluationByKey(int $evaluationId): array
    {
        $out = [];
        foreach ($this->where('evaluation_id', $evaluationId)->findAll() as $row) {
            $out[(string) $row['competency_key']] = $row;
        }
        return $out;
    }
}
