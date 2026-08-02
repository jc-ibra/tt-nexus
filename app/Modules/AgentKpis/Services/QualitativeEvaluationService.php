<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\AgentKpis\Config\AgentKpis;
use App\Modules\AgentKpis\Models\MonthlyEvaluationModel;
use App\Modules\AgentKpis\Models\QualitativeScoreModel;

/**
 * Handles the qualitative rubric (8 fixed competencies) and rolls it up into the
 * qualitative score and the final score. Blocked evaluations (KPI 5 >= 3) never
 * get a final score.
 */
class QualitativeEvaluationService
{
    public function __construct(
        private MonthlyEvaluationModel $evaluations,
        private QualitativeScoreModel $scores,
    ) {}

    /**
     * Current rubric for an evaluation: the 8 competencies with any captured
     * score/evidence merged in.
     *
     * @return array<int,array{key:string,name:string,weight:float,score:?int,evidence:string}>
     */
    public function getRubric(int $evaluationId): array
    {
        $saved = $this->scores->forEvaluationByKey($evaluationId);
        $out   = [];
        foreach (AgentKpis::COMPETENCIES as $c) {
            $row = $saved[$c['key']] ?? null;
            $out[] = [
                'key'      => $c['key'],
                'name'     => $c['name'],
                'weight'   => $c['weight'],
                'score'    => $row && $row['score'] !== null ? (int) $row['score'] : null,
                'evidence' => (string) ($row['evidence'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Persists the rubric and recomputes the qualitative + final scores.
     *
     * @param array<string,int|string>    $scores    competency_key => 1..4
     * @param array<string,string>        $evidence  competency_key => text
     */
    public function saveRubric(int $evaluationId, array $scores, array $evidence, ?int $byUserId): ServiceResult
    {
        $eval = $this->evaluations->find($evaluationId);
        if ($eval === null) {
            return ServiceResult::fail('Evaluación no encontrada.');
        }

        $raw = 0.0;
        foreach (AgentKpis::COMPETENCIES as $c) {
            $score = (int) ($scores[$c['key']] ?? AgentKpis::DEFAULT_SCORE);
            $score = max(1, min(4, $score));
            $raw  += $score * $c['weight'];

            $existing = $this->scores->where('evaluation_id', $evaluationId)->where('competency_key', $c['key'])->first();
            $row = [
                'evaluation_id'   => $evaluationId,
                'competency_key'  => $c['key'],
                'competency_name' => $c['name'],
                'weight'          => $c['weight'],
                'score'           => $score,
                'evidence'        => trim((string) ($evidence[$c['key']] ?? '')),
            ];
            if ($existing) {
                $this->scores->update((int) $existing['id'], $row);
            } else {
                $this->scores->insert($row);
            }
        }

        $raw          = round($raw, 2);                    // 1.00 .. 4.00
        $qualitative  = round($raw / 4.0 * 0.20 * 100, 2); // 0 .. 20
        $isBlocked    = (int) $eval['is_blocked'] === 1;
        $quantScore   = (float) ($eval['quantitative_score'] ?? 0);

        $update = [
            'qualitative_score_raw' => $raw,
            'qualitative_score'     => $qualitative,
            'evaluated_by_user_id'  => $byUserId,
            'evaluated_at'          => date('Y-m-d H:i:s'),
        ];
        if ($isBlocked) {
            $update['final_status'] = 'blocked';
            $update['final_score']  = null;
        } else {
            $update['final_status'] = 'evaluated';
            $update['final_score']  = round($quantScore + $qualitative, 2);
        }

        $this->evaluations->update($evaluationId, $update);

        return ServiceResult::ok(['qualitative' => $qualitative, 'final' => $update['final_score']], 'Rúbrica guardada.');
    }

    /** Saves supervisor notes / agent comments without touching scores. */
    public function saveNotes(int $evaluationId, ?string $supervisorNotes, ?string $agentComments): void
    {
        $data = [];
        if ($supervisorNotes !== null) {
            $data['supervisor_notes'] = trim($supervisorNotes);
        }
        if ($agentComments !== null) {
            $data['agent_comments'] = trim($agentComments);
        }
        if ($data !== []) {
            $this->evaluations->update($evaluationId, $data);
        }
    }
}
