<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

/**
 * The GLPI documentation templates (spec §6) as data. Each action declares its
 * template number, GLPI operation type (followup | pending | solution), the
 * resulting status, and the variable fields the bot collects. render() fills the
 * template text; the ConversationService drives the collection.
 *
 * Automatic fields:
 *   {nombre_tecnico} -> the linked employee's name (passed as _nombre_tecnico)
 *   {ticket_ref}     -> the GLPI ticket id (passed as _ticket_ref)
 *   {hora_llegada}   -> current time for TEMPLATE_EN_SITIO (passed as _now_hm)
 */
class TemplateService
{
    // Action keys (also used as the conversation-state suffixes and log actions).
    public const A_EN_CAMINO             = 'en_camino';
    public const A_EN_SITIO              = 'en_sitio';
    public const A_REPROGRAMACION        = 'reprogramacion';
    public const A_DIAGNOSTICO           = 'diagnostico';
    public const A_PENDIENTE_CLIENTE     = 'pendiente_cliente';
    public const A_PENDIENTE_REFACCION   = 'pendiente_refaccion';
    public const A_RES_SIN_REFACCION     = 'resolucion_sin_refaccion';
    public const A_RES_CON_REFACCION     = 'resolucion_con_refaccion';
    public const A_RES_REMOTA            = 'resolucion_remota';
    public const A_RES_ARBITRARIA        = 'resolucion_arbitraria';

    public const TYPE_FOLLOWUP = 'followup';
    public const TYPE_PENDING  = 'pending';   // followup + move to status 4
    public const TYPE_SOLUTION = 'solution';

    /**
     * Static metadata for each action. `fields` lists the variable fields the bot
     * asks for, in order. `ai` marks a free-text field that may be reformatted
     * with Claude.
     */
    public const ACTIONS = [
        self::A_EN_CAMINO => [
            'label'        => 'En camino',
            'template_key' => 'TEMPLATE_EN_CAMINO',
            'template_no'  => 3,
            'type'         => self::TYPE_FOLLOWUP,
            'status_after' => GlpiFieldService::STATUS_PROCESSING,
        ],
        self::A_EN_SITIO => [
            'label'        => 'En sitio',
            'template_key' => 'TEMPLATE_EN_SITIO',
            'template_no'  => 4,
            'type'         => self::TYPE_FOLLOWUP,
            'status_after' => GlpiFieldService::STATUS_PROCESSING,
        ],
        self::A_REPROGRAMACION => [
            'label'        => 'Reprogramar',
            'template_key' => 'TEMPLATE_REPROGRAMACION',
            'template_no'  => 5,
            'type'         => self::TYPE_FOLLOWUP,
            'status_after' => GlpiFieldService::STATUS_PROCESSING,
        ],
        self::A_DIAGNOSTICO => [
            'label'        => 'Diagnóstico',
            'template_key' => 'TEMPLATE_DIAGNOSTICO',
            'template_no'  => 6,
            'type'         => self::TYPE_FOLLOWUP,
            'status_after' => GlpiFieldService::STATUS_PROCESSING,
        ],
        self::A_PENDIENTE_CLIENTE => [
            'label'        => 'Pendiente: cliente',
            'template_key' => 'TEMPLATE_PENDIENTE_CLIENTE',
            'template_no'  => 7,
            'type'         => self::TYPE_PENDING,
            'status_after' => GlpiFieldService::STATUS_WAITING,
        ],
        self::A_PENDIENTE_REFACCION => [
            'label'        => 'Pendiente: refacción',
            'template_key' => 'TEMPLATE_PENDIENTE_REFACCION',
            'template_no'  => 8,
            'type'         => self::TYPE_PENDING,
            'status_after' => GlpiFieldService::STATUS_WAITING,
        ],
        self::A_RES_SIN_REFACCION => [
            'label'        => 'Sin refacción',
            'template_key' => 'TEMPLATE_RESOLUCION_SIN_REFACCION',
            'template_no'  => 9,
            'type'         => self::TYPE_SOLUTION,
            'status_after' => GlpiFieldService::STATUS_SOLVED,
        ],
        self::A_RES_CON_REFACCION => [
            'label'        => 'Con refacción',
            'template_key' => 'TEMPLATE_RESOLUCION_CON_REFACCION',
            'template_no'  => 10,
            'type'         => self::TYPE_SOLUTION,
            'status_after' => GlpiFieldService::STATUS_SOLVED,
        ],
        self::A_RES_REMOTA => [
            'label'        => 'Remota',
            'template_key' => 'TEMPLATE_RESOLUCION_REMOTA',
            'template_no'  => 12,
            'type'         => self::TYPE_SOLUTION,
            'status_after' => GlpiFieldService::STATUS_SOLVED,
        ],
        self::A_RES_ARBITRARIA => [
            'label'        => 'Cierre administrativo',
            'template_key' => 'TEMPLATE_RESOLUCION_ARBITRARIA',
            'template_no'  => 11,
            'type'         => self::TYPE_SOLUTION,
            'status_after' => GlpiFieldService::STATUS_SOLVED,
        ],
    ];

    public function meta(string $action): ?array
    {
        return self::ACTIONS[$action] ?? null;
    }

    public function label(string $action): string
    {
        return self::ACTIONS[$action]['label'] ?? $action;
    }

    /** Whether an action's free-text field is a candidate for AI formatting. */
    public function isAiCandidate(string $action): bool
    {
        return in_array($action, [
            self::A_DIAGNOSTICO,
            self::A_RES_SIN_REFACCION,
            self::A_RES_CON_REFACCION,
            self::A_RES_REMOTA,
        ], true);
    }

    /**
     * Renders the final GLPI text for an action from the collected data plus the
     * automatic fields (_nombre_tecnico, _ticket_ref, _now_hm).
     */
    public function render(string $action, array $d): string
    {
        $tech   = trim((string) ($d['_nombre_tecnico'] ?? ''));
        $ref    = trim((string) ($d['_ticket_ref'] ?? ''));
        $nowHm  = trim((string) ($d['_now_hm'] ?? date('H:i')));

        return match ($action) {
            self::A_EN_CAMINO => "Se inicia traslado hacia el sitio de atencion. Arribo estimado a las "
                . ($d['hora_estimada'] ?? '') . " hrs.\nTecnico asignado: {$tech}",

            self::A_EN_SITIO => "{$tech} se ha presentado en sitio a las {$nowHm} hrs. "
                . "Se da inicio a la atencion del ticket {$ref}.",

            self::A_REPROGRAMACION => "El ticket {$ref} ha sido reprogramado.\n"
                . "Nueva fecha y hora de atencion: " . ($d['nueva_fecha_hora'] ?? '') . " hrs.\n\n"
                . "Se agradece la comprension.",

            self::A_DIAGNOSTICO => "Se ha concluido el diagnostico del ticket {$ref}\n\n"
                . "Diagnostico realizado:\n\n" . ($d['diagnostico'] ?? ''),

            self::A_PENDIENTE_CLIENTE => "El ticket {$ref} se encuentra en espera de respuesta o accion "
                . "del solicitante para continuar con la atencion.\n\n"
                . "Detalle: " . ($d['detalle'] ?? '') . "\n\n"
                . "Favor de contactar al ejecutivo asignado o responder a la brevedad.",

            self::A_PENDIENTE_REFACCION => "El ticket {$ref} se encuentra en espera de las refacciones "
                . "requeridas para su resolucion.\n\n"
                . "Refacciones requeridas: " . ($d['refacciones'] ?? '') . "\n\n"
                . "Se notificara en cuanto esten disponibles.",

            self::A_RES_SIN_REFACCION => "El ticket {$ref} ha sido resuelto satisfactoriamente.\n\n"
                . "Pasos realizados:\n" . ($d['pasos_realizados'] ?? '') . "\n\n"
                . "Fecha y hora de inicio: " . ($d['fecha_inicio'] ?? '') . "\n"
                . "Fecha y hora de termino: " . ($d['fecha_termino'] ?? '') . "\n"
                . "Visto Bueno: " . ($d['visto_bueno'] ?? '') . "\n\n"
                . $this->solutionFooter(),

            self::A_RES_CON_REFACCION => "El ticket {$ref} ha sido resuelto satisfactoriamente con uso de refacciones.\n\n"
                . "Pasos realizados:\n" . ($d['pasos_realizados'] ?? '') . "\n\n"
                . "Refacciones utilizadas: " . ($d['refacciones_utilizadas'] ?? '') . "\n\n"
                . "Fecha y hora de inicio: " . ($d['fecha_inicio'] ?? '') . "\n"
                . "Fecha y hora de termino: " . ($d['fecha_termino'] ?? '') . "\n"
                . "Visto Bueno: " . ($d['visto_bueno'] ?? '') . "\n\n"
                . $this->solutionFooter(),

            self::A_RES_REMOTA => "El ticket {$ref} ha sido resuelto satisfactoriamente de forma remota.\n\n"
                . "Modalidad de atencion: " . ($d['modalidad'] ?? '') . "\n\n"
                . "Pasos realizados:\n" . ($d['pasos_realizados'] ?? '') . "\n\n"
                . "Fecha y hora de inicio: " . ($d['fecha_inicio'] ?? '') . "\n"
                . "Fecha y hora de termino: " . ($d['fecha_termino'] ?? '') . "\n"
                . "Visto Bueno: " . ($d['visto_bueno'] ?? '') . "\n\n"
                . $this->solutionFooter(),

            self::A_RES_ARBITRARIA => "El ticket {$ref} ha sido cerrado.\n\n"
                . "Motivo del cierre: " . ($d['motivo'] ?? '') . "\n"
                . "Persona que solicita cierre: " . ($d['persona_solicita'] ?? '') . "\n\n"
                . "De considerar que el caso no fue resuelto, es posible reabrirlo o contactar a MAC.",

            default => '',
        };
    }

    private function solutionFooter(): string
    {
        return "El ticket queda en estado Resuelto. Se solicita confirmar la conformidad del servicio.\n"
            . "De no recibirse respuesta en un plazo de 2 dias habiles, el ticket pasara automaticamente al estado Cerrado.";
    }
}
