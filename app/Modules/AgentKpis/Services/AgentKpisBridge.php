<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Services;

use App\Modules\AgentKpis\Models\KpiSnapshotModel;
use App\Modules\AgentKpis\Models\MonthlyEvaluationModel;
use App\Modules\Core\Services\ServiceResult;

/**
 * Read-mostly bridge over the AgentKpis data for the agent self-view living in
 * Service Desk ("Mis evaluaciones"). Mirrors the role HelpdeskSupervisorBridge
 * plays for "Mi desempeño": other modules go through this service, never the
 * tables or the supervisor controllers.
 *
 * Two guarantees are enforced HERE, not in the calling controller:
 *  - ownership: every read/write is scoped to the caller's nexus_user_id;
 *  - publication: only evaluations the supervisor already closed are exposed,
 *    so an agent never sees a draft or a half-computed month.
 */
class AgentKpisBridge
{
    /**
     * Statuses an agent may see. 'draft' and 'pending_qualitative' stay hidden
     * until the rubric is captured; 'blocked' IS shown, with its explanation,
     * because that is precisely the case where the right of reply matters.
     */
    public const VISIBLE_STATUSES = ['evaluated', 'blocked'];

    public function __construct(
        private MonthlyEvaluationModel $evaluations,
        private KpiSnapshotModel $snapshots,
        private QualitativeEvaluationService $qualitative,
    ) {}

    /** Closed evaluations of an agent, newest first. @return array<int,array<string,mixed>> */
    public function publishedForAgent(int $nexusUserId): array
    {
        if ($nexusUserId <= 0) {
            return [];
        }
        return $this->evaluations->publishedForAgent($nexusUserId, self::VISIBLE_STATUSES);
    }

    /** Most recent closed evaluation, for the summary card. */
    public function latestForAgent(int $nexusUserId): ?array
    {
        return $this->publishedForAgent($nexusUserId)[0] ?? null;
    }

    /** One evaluation, or null if it is not the agent's own or not published yet. */
    public function evaluationForAgent(int $nexusUserId, int $evaluationId): ?array
    {
        if ($nexusUserId <= 0 || $evaluationId <= 0) {
            return null;
        }
        return $this->evaluations->findForAgent($evaluationId, $nexusUserId, self::VISIBLE_STATUSES);
    }

    /**
     * KPI snapshots of an evaluation the agent owns. Returns [] for anything
     * else, so a forged id can never leak another agent's numbers.
     */
    public function snapshotsForAgent(int $nexusUserId, int $evaluationId): array
    {
        if ($this->evaluationForAgent($nexusUserId, $evaluationId) === null) {
            return [];
        }
        return $this->snapshots->forEvaluation($evaluationId);
    }

    /** Qualitative rubric (8 competencies) of an evaluation the agent owns. */
    public function rubricForAgent(int $nexusUserId, int $evaluationId): array
    {
        if ($this->evaluationForAgent($nexusUserId, $evaluationId) === null) {
            return [];
        }
        return $this->qualitative->getRubric($evaluationId);
    }

    /**
     * Right of reply: the agent writes their own comments on their own
     * evaluation. Never touches scores, notes or any other column.
     */
    public function saveAgentComments(int $nexusUserId, int $evaluationId, string $comments): ServiceResult
    {
        if ($this->evaluationForAgent($nexusUserId, $evaluationId) === null) {
            return ServiceResult::fail('Evaluación no disponible.');
        }

        $comments = trim($comments);
        if (mb_strlen($comments) > 5000) {
            return ServiceResult::fail('Tus comentarios no pueden exceder 5000 caracteres.');
        }

        $this->evaluations->update($evaluationId, ['agent_comments' => $comments]);
        return ServiceResult::ok(null, 'Tus comentarios quedaron registrados.');
    }

    // ------------------------------------------------------------------
    // Period-wide summary (Reports module)
    // ------------------------------------------------------------------

    /**
     * Aggregate performance snapshot for a natural month, for the Reports
     * module's "Desempeño de Agentes" section. Includes EVERY evaluation
     * regardless of final_status (unlike publishedForAgent): this is the
     * supervisor's own executive report, not the agent-facing view, so a
     * draft or blocked month is exactly what direction needs to see.
     *
     * @return array{available:bool,agents?:array,avg_final_score?:float,evaluated_count?:int,blocked_count?:int}
     */
    public function periodEvaluationSummary(int $year, int $month): array
    {
        $rows = $this->evaluations->forMonth($year, $month);
        if ($rows === []) {
            return ['available' => false];
        }

        $scored = array_filter($rows, static fn(array $r) => $r['final_score'] !== null);
        $avg    = $scored !== []
            ? round(array_sum(array_map(static fn(array $r) => (float) $r['final_score'], $scored)) / count($scored), 2)
            : 0.0;

        return [
            'available'       => true,
            'agents'          => array_map(static fn(array $r) => [
                'nexus_user_id'      => (int) $r['nexus_user_id'],
                'agent_name'         => (string) $r['agent_name'],
                'total_tickets'      => (int) $r['total_tickets'],
                'kpis_met_count'     => (int) $r['kpis_met_count'],
                'quantitative_score' => (float) $r['quantitative_score'],
                'qualitative_score'  => $r['qualitative_score'] !== null ? (float) $r['qualitative_score'] : null,
                'final_score'        => $r['final_score'] !== null ? (float) $r['final_score'] : null,
                'final_status'       => (string) $r['final_status'],
                'is_blocked'         => (bool) $r['is_blocked'],
            ], $rows),
            'avg_final_score' => $avg,
            'evaluated_count' => count(array_filter($rows, static fn(array $r) => $r['final_status'] === 'evaluated')),
            'blocked_count'   => count(array_filter($rows, static fn(array $r) => (bool) $r['is_blocked'])),
        ];
    }
}
