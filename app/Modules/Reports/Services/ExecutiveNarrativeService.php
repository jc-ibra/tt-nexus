<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Core\Services\AiClient;
use App\Modules\Core\Services\ServiceResult;
use App\Modules\Reports\Models\ReportCommentaryModel;

/**
 * Drafts an executive summary from an already-frozen payload_json (never
 * from raw tickets) via the shared AiClient. The result is always saved as
 * an editable DRAFT (is_ai_draft=1) in reports_commentary — it is never
 * shown to direction until someone reviews and re-saves it, same principle
 * NotificationDraftService follows for HelpdeskSupervisor's notifications.
 */
class ExecutiveNarrativeService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Eres un analista que redacta el resumen ejecutivo de un informe mensual de
        mesa de ayuda para dirección. Recibes un JSON con las métricas ya
        calculadas del mes (tickets GLPI, tendencia, correo, calidad documental,
        desempeño de agentes). Escribe en español, tono directo y profesional,
        sin inventar cifras que no estén en el JSON. Estructura la respuesta en
        párrafos cortos, uno por sección relevante, cerrando con los puntos que
        requieren atención de dirección. No uses guiones largos (—) ni emojis.
        PROMPT;

    public function __construct(
        private ReportsSettingsService $settings,
        private ReportCommentaryModel $commentary,
    ) {}

    public function isReady(): bool
    {
        return $this->settings->aiReady();
    }

    /**
     * Generates the narrative and stores it as a draft for section 'summary'.
     */
    public function draftSummary(int $snapshotId, array $payload, ?int $authorId): ServiceResult
    {
        if (! $this->isReady()) {
            return ServiceResult::fail('La generación con IA no está configurada.');
        }

        $client = new AiClient($this->settings->aiApiKey());
        $result = $client->completeText(
            $this->settings->aiModel(),
            self::SYSTEM_PROMPT,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            2048,
        );

        if (! $result['ok']) {
            return ServiceResult::fail('No se pudo generar el resumen: ' . ($result['error'] ?? 'error desconocido.'));
        }

        $this->commentary->upsert($snapshotId, 'summary', $result['text'], true, $authorId);

        return ServiceResult::ok(['text' => $result['text']], 'Borrador de resumen ejecutivo generado.');
    }
}
