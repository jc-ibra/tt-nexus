<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\Core\Services\AiClient;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Services\Support\DeviationSummary;

/**
 * Generates the per-agent email draft with Claude (Haiku). The IA only writes
 * the email from the deviations the module already computed; it never analyzes
 * tickets or decides what is a deviation.
 */
class NotificationDraftService
{
    private const SYSTEM_PROMPT = <<<'TXT'
Eres un asistente que redacta correos profesionales en español para el Gerente de Service Desk de Trantor Technologies. Tu tarea es redactar un correo dirigido a un agente de mesa de ayuda informándole sobre las desviaciones encontradas en su operación de tickets en GLPI durante el período indicado.

El correo debe:
- Ser profesional, directo y constructivo. No es un regaño; es una notificación con el objetivo de que el agente corrija y mejore.
- Incluir un saludo con el nombre del agente.
- Resumir las desviaciones agrupadas por tipo de regla.
- Para cada grupo, indicar cuántas ocurrencias hubo y dar un ejemplo concreto (con número de ticket).
- Incluir la acción correctiva esperada, referenciando la sección del manual que aplica.
- Cerrar indicando que se adjunta un archivo Excel con el detalle completo.
- Firmar a nombre del supervisor (nombre proporcionado).
- No usar emojis ni el símbolo de raya em. Usar guion simple o dos puntos.
- Formato HTML sencillo (párrafos, listas, negritas). Sin imágenes. Devuelve SOLO el HTML del cuerpo, sin ```html ni explicaciones.
TXT;

    public function __construct(
        private HelpdeskSupervisorSettings $settings,
        private DeviationModel $deviations,
        private AuditRunModel $runs,
    ) {}

    /**
     * @return array{ok:bool,error?:string,draft_html?:string,input_tokens?:int,output_tokens?:int,total_deviations?:int,agent_name?:string,nexus_user_id?:?int}
     */
    public function generateDraft(int $auditRunId, int $glpiUserId, string $supervisorName = '', string $supervisorTitle = 'Gerente de Service Desk'): array
    {
        $run = $this->runs->find($auditRunId);
        if ($run === null) {
            return ['ok' => false, 'error' => 'Auditoría no encontrada.'];
        }

        $rows = $this->deviations->forAgent($auditRunId, $glpiUserId);
        if ($rows === []) {
            return ['ok' => false, 'error' => 'El agente no tiene desviaciones en esta auditoría.'];
        }

        $agentName   = (string) ($rows[0]['agent_name'] ?? '');
        $nexusUserId = $rows[0]['nexus_user_id'] ?? null;
        $grouped     = DeviationSummary::group($rows, 3);
        $ticketCount = count(array_unique(array_map(static fn($r) => (int) $r['glpi_ticket_id'], $rows)));

        if ($supervisorName === '') {
            $supervisorName = $this->settings->notificationSenderName() ?: 'Gerencia de Service Desk';
        }

        if (! $this->settings->aiReady()) {
            return [
                'ok' => false, 'error' => 'La IA no está configurada (falta API key).',
                'total_deviations' => count($rows), 'agent_name' => $agentName, 'nexus_user_id' => $nexusUserId,
            ];
        }

        $user = $this->buildUserMessage($agentName, (string) $run['period_start'], (string) $run['period_end'],
            $ticketCount, count($rows), $supervisorName, $supervisorTitle, $grouped);

        $client = new AiClient($this->settings->aiApiKey());
        $res    = $client->completeText($this->settings->aiModel(), self::SYSTEM_PROMPT, $user, $this->settings->aiMaxTokens());

        if (! $res['ok']) {
            return [
                'ok' => false, 'error' => 'No se pudo contactar a la IA: ' . ($res['error'] ?? ''),
                'total_deviations' => count($rows), 'agent_name' => $agentName, 'nexus_user_id' => $nexusUserId,
            ];
        }

        return [
            'ok'               => true,
            'draft_html'       => $this->stripCodeFence($res['text']),
            'input_tokens'     => $res['input_tokens'],
            'output_tokens'    => $res['output_tokens'],
            'total_deviations' => count($rows),
            'agent_name'       => $agentName,
            'nexus_user_id'    => $nexusUserId,
        ];
    }

    /** Removes a wrapping markdown code fence (```html ... ```) if the model added one. */
    private function stripCodeFence(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            // Drop the opening fence line (``` or ```html) and the closing fence.
            $text = preg_replace('/^```[a-zA-Z]*\s*\n?/', '', $text) ?? $text;
            $text = preg_replace('/\n?```\s*$/', '', $text) ?? $text;
        }
        return trim($text);
    }

    /** @param array<int,array<string,mixed>> $grouped */
    private function buildUserMessage(
        string $agent, string $start, string $end, int $tickets, int $totalDeviations,
        string $supervisorName, string $supervisorTitle, array $grouped
    ): string {
        $json = json_encode($grouped, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "Redacta el correo con los siguientes datos:\n\n"
            . "Agente: {$agent}\n"
            . "Período: {$start} a {$end}\n"
            . "Tickets con desviaciones: {$tickets}\n"
            . "Total de desviaciones: {$totalDeviations}\n"
            . "Firma del remitente: {$supervisorName}, {$supervisorTitle}\n\n"
            . "Desviaciones por regla (JSON, máximo 3 ejemplos por regla; el Excel adjunto lleva el detalle completo):\n"
            . $json;
    }
}
