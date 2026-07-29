<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

use App\Modules\TechBot\Models\ActivityLogModel;
use App\Modules\TechBot\Models\AiUsageModel;
use App\Modules\TechBot\Models\ConversationStateModel;

/**
 * The conversation state machine for a LINKED technician (spec §6-7).
 *
 * It receives normalized events from TelegramWebhookService (text, photo,
 * callback) together with the technician's active link, reads/advances the
 * persisted state, and drives every documentation flow to a GLPI followup or
 * solution.
 *
 * Flows are generic: entering an action builds a QUEUE of fields to collect;
 * text/choice/multiline steps are consumed off the queue until it is empty, then
 * a confirmation gate executes the action in GLPI. This keeps all ten templates
 * on one engine instead of ten bespoke branches.
 */
class ConversationService
{
    private const S_IDLE            = 'idle';
    private const S_TICKET_MENU     = 'ticket_menu';
    private const S_PENDIENTE_TIPO  = 'pendiente_tipo';
    private const S_RESOLUCION_TIPO = 'resolucion_tipo';
    private const S_IN_FLOW         = 'in_flow';        // collecting queue[0]
    private const S_CHOOSE_FORMAT   = 'choose_format';  // pick original vs AI text
    private const S_CONFIRM         = 'confirm';        // final yes/no gate

    public function __construct(
        private TelegramApiService $telegram,
        private TechBotSettingsService $settings,
        private TemplateService $templates,
        private GlpiFieldService $glpi,
        private AiFormatterService $ai,
        private ConversationStateModel $states,
        private ActivityLogModel $activity,
        private AiUsageModel $aiUsage,
    ) {}

    /**
     * Entry point for a linked technician's event.
     *
     * @param array $link  the active techbot_telegram_links row
     * @param array $event ['type'=>'command|text|callback|photo', 'chat_id'=>int, ...]
     */
    public function handle(array $link, array $event): void
    {
        $chatId = (int) $link['telegram_chat_id'];
        $type   = (string) ($event['type'] ?? '');

        // Acknowledge callback taps immediately (stops Telegram's spinner).
        if ($type === 'callback' && ! empty($event['callback_id'])) {
            $this->telegram->answerCallbackQuery((string) $event['callback_id']);
        }

        // Global commands are valid in any state.
        if ($type === 'command') {
            $this->handleCommand($link, $chatId, (string) ($event['command'] ?? ''));
            return;
        }

        $snapshot = $this->states->loadState($chatId);
        if ($snapshot['expired']) {
            $this->states->reset($chatId);
            $this->telegram->sendMessage($chatId, 'La sesión expiró por inactividad. Usa /tickets para volver a empezar.');
            return;
        }

        $state   = $snapshot['state'];
        $context = $snapshot['context'];

        match ($type) {
            'callback' => $this->handleCallback($link, $chatId, $state, $context, (string) ($event['data'] ?? '')),
            'text'     => $this->handleText($link, $chatId, $state, $context, trim((string) ($event['text'] ?? ''))),
            'photo'    => $this->handlePhoto($link, $chatId, $state, $context, (string) ($event['photo_file_id'] ?? '')),
            default    => $this->telegram->sendMessage($chatId, 'No entendí ese mensaje. Usa /tickets para ver tus tickets o /ayuda para ayuda.'),
        };
    }

    // ------------------------------------------------------------------
    // Commands
    // ------------------------------------------------------------------

    private function handleCommand(array $link, int $chatId, string $command): void
    {
        switch ($command) {
            case 'start':
                $this->telegram->sendMessage($chatId, 'Hola ' . $this->firstName($link) . '. Aquí puedes documentar tus tickets asignados.');
                $this->showTickets($link, $chatId);
                break;
            case 'tickets':
                $this->showTickets($link, $chatId);
                break;
            case 'cancelar':
                $this->states->reset($chatId);
                $this->telegram->sendMessage($chatId, 'Flujo cancelado. Usa /tickets para ver tus tickets.');
                break;
            case 'ayuda':
            default:
                $this->telegram->sendMessage($chatId, $this->helpText());
                break;
        }
    }

    private function helpText(): string
    {
        return "Bot de Soporte · Ayuda\n\n"
            . "/tickets - Ver tus tickets asignados y documentarlos\n"
            . "/cancelar - Cancelar el flujo actual\n"
            . "/ayuda - Mostrar esta ayuda\n\n"
            . "Selecciona un ticket para registrar: en camino, en sitio, diagnóstico, "
            . "reprogramación, pendiente o resolución.";
    }

    // ------------------------------------------------------------------
    // Ticket list + menu
    // ------------------------------------------------------------------

    private function showTickets(array $link, int $chatId): void
    {
        if (! $this->settings->botEnabled()) {
            $this->telegram->sendMessage($chatId, 'El bot está temporalmente deshabilitado. Intenta más tarde.');
            return;
        }

        $tickets = $this->glpi->getAssignedTickets((int) $link['glpi_user_id']);
        $this->states->reset($chatId);

        if ($tickets === []) {
            $this->telegram->sendMessage($chatId, 'No tienes tickets abiertos asignados en este momento.');
            return;
        }

        $keyboard = [];
        foreach ($tickets as $t) {
            $keyboard[] = [[
                'text'          => "#{$t['id']} · " . $this->shorten($t['title'], 40) . " · {$t['status_label']}",
                'callback_data' => 'ticket:' . $t['id'],
            ]];
        }

        $this->telegram->sendMessage($chatId, 'Tus tickets asignados (' . count($tickets) . '). Selecciona uno:', $keyboard);
    }

    private function showTicketMenu(array $link, int $chatId, int $ticketId): void
    {
        $tickets = $this->glpi->getAssignedTickets((int) $link['glpi_user_id']);
        $ticket  = null;
        foreach ($tickets as $t) {
            if ($t['id'] === $ticketId) {
                $ticket = $t;
                break;
            }
        }

        if ($ticket === null) {
            $this->telegram->sendMessage($chatId, 'Ese ticket ya no está en tu lista de asignados o fue cerrado. Usa /tickets para actualizar.');
            $this->states->reset($chatId);
            return;
        }

        $summary = "Ticket #{$ticket['id']} · {$ticket['title']}\n"
            . "Estado: {$ticket['status_label']}"
            . ($ticket['entity'] !== '' ? " · {$ticket['entity']}" : '')
            . ($ticket['date'] !== '' ? "\nAbierto: {$ticket['date']}" : '');

        $keyboard = [
            [
                ['text' => 'En camino',   'callback_data' => 'action:' . TemplateService::A_EN_CAMINO],
                ['text' => 'En sitio',    'callback_data' => 'action:' . TemplateService::A_EN_SITIO],
            ],
            [
                ['text' => 'Diagnóstico', 'callback_data' => 'action:' . TemplateService::A_DIAGNOSTICO],
                ['text' => 'Reprogramar', 'callback_data' => 'action:' . TemplateService::A_REPROGRAMACION],
            ],
            [
                ['text' => 'Pendiente',   'callback_data' => 'menu:pendiente'],
                ['text' => 'Resolver',    'callback_data' => 'menu:resolver'],
            ],
            [
                ['text' => '‹ Volver a la lista', 'callback_data' => 'back_list'],
            ],
        ];

        $this->states->saveState($chatId, self::S_TICKET_MENU, ['ticket' => $ticket], $ticketId);
        $this->telegram->sendMessage($chatId, $summary, $keyboard);
    }

    // ------------------------------------------------------------------
    // Callback routing
    // ------------------------------------------------------------------

    private function handleCallback(array $link, int $chatId, string $state, array $context, string $data): void
    {
        // Universal navigation.
        if ($data === 'back_list') {
            $this->showTickets($link, $chatId);
            return;
        }
        if (str_starts_with($data, 'ticket:')) {
            $this->showTicketMenu($link, $chatId, (int) substr($data, 7));
            return;
        }

        $ticketId = $this->currentTicketId($chatId);
        if ($ticketId === null) {
            $this->telegram->sendMessage($chatId, 'La sesión se reinició. Usa /tickets para continuar.');
            $this->states->reset($chatId);
            return;
        }

        // Menu → action selection.
        if ($data === 'menu:pendiente') {
            $this->askPendienteType($chatId, $context, $ticketId);
            return;
        }
        if ($data === 'menu:resolver') {
            $this->askResolucionType($chatId, $context, $ticketId);
            return;
        }
        if (str_starts_with($data, 'action:')) {
            $this->startFlow($link, $chatId, substr($data, 7), $context, $ticketId);
            return;
        }
        if (str_starts_with($data, 'pend:')) {
            $key = substr($data, 5) === 'refaccion' ? TemplateService::A_PENDIENTE_REFACCION : TemplateService::A_PENDIENTE_CLIENTE;
            $this->startFlow($link, $chatId, $key, $context, $ticketId);
            return;
        }
        if (str_starts_with($data, 'res:')) {
            $map = [
                'sin'        => TemplateService::A_RES_SIN_REFACCION,
                'con'        => TemplateService::A_RES_CON_REFACCION,
                'remota'     => TemplateService::A_RES_REMOTA,
                'arbitraria' => TemplateService::A_RES_ARBITRARIA,
            ];
            $key = $map[substr($data, 4)] ?? null;
            if ($key === null || ($key === TemplateService::A_RES_ARBITRARIA && ! $this->settings->allowResolucionArbitraria())) {
                $this->telegram->sendMessage($chatId, 'Esa opción no está disponible.');
                return;
            }
            $this->startFlow($link, $chatId, $key, $context, $ticketId);
            return;
        }

        // In-flow controls.
        if ($state === self::S_IN_FLOW && str_starts_with($data, 'choice:')) {
            $this->applyChoice($link, $chatId, $context, substr($data, 7));
            return;
        }
        if ($state === self::S_IN_FLOW && $data === 'done') {
            $this->finishMultiline($link, $chatId, $context);
            return;
        }
        if ($state === self::S_CHOOSE_FORMAT && str_starts_with($data, 'fmt:')) {
            $this->applyFormatChoice($link, $chatId, $context, substr($data, 4));
            return;
        }
        if ($state === self::S_CONFIRM && str_starts_with($data, 'confirm:')) {
            if (substr($data, 8) === 'yes') {
                $this->execute($link, $chatId, $context);
            } else {
                $this->states->reset($chatId);
                $this->telegram->sendMessage($chatId, 'Acción cancelada. Usa /tickets para continuar.');
            }
            return;
        }

        $this->telegram->sendMessage($chatId, 'Opción no válida en este momento. Usa /tickets para reiniciar.');
    }

    private function askPendienteType(int $chatId, array $context, int $ticketId): void
    {
        $this->states->saveState($chatId, self::S_PENDIENTE_TIPO, $context, $ticketId);
        $this->telegram->sendMessage($chatId, '¿Qué tipo de pendiente?', [
            [['text' => 'Cliente / solicitante', 'callback_data' => 'pend:cliente']],
            [['text' => 'Refacción', 'callback_data' => 'pend:refaccion']],
            [['text' => '‹ Volver', 'callback_data' => 'ticket:' . $ticketId]],
        ]);
    }

    private function askResolucionType(int $chatId, array $context, int $ticketId): void
    {
        $this->states->saveState($chatId, self::S_RESOLUCION_TIPO, $context, $ticketId);
        $rows = [
            [['text' => 'Sin refacción', 'callback_data' => 'res:sin']],
            [['text' => 'Con refacción', 'callback_data' => 'res:con']],
            [['text' => 'Remota', 'callback_data' => 'res:remota']],
        ];
        if ($this->settings->allowResolucionArbitraria()) {
            $rows[] = [['text' => 'Cierre administrativo', 'callback_data' => 'res:arbitraria']];
        }
        $rows[] = [['text' => '‹ Volver', 'callback_data' => 'ticket:' . $ticketId]];
        $this->telegram->sendMessage($chatId, '¿Qué tipo de resolución?', $rows);
    }

    // ------------------------------------------------------------------
    // Flow engine
    // ------------------------------------------------------------------

    /**
     * Field queue per action. kind: 'text' (one message), 'multiline' (many
     * messages + photos, ended with Listo), 'choice' (inline buttons).
     */
    private function queueFor(string $action): array
    {
        $paso    = ['key' => 'pasos_realizados', 'label' => 'Describe los pasos realizados. Puedes enviar varios mensajes y fotos; pulsa «Listo» al terminar.', 'kind' => 'multiline', 'ai' => true];
        $inicio  = ['key' => 'fecha_inicio', 'label' => 'Fecha y hora de INICIO (por ejemplo 09:30 o 15/07/2026 09:30).', 'kind' => 'text'];
        $termino = ['key' => 'fecha_termino', 'label' => 'Fecha y hora de TÉRMINO.', 'kind' => 'text'];
        $vb      = ['key' => 'visto_bueno', 'label' => 'Nombre de la persona que dio el Visto Bueno.', 'kind' => 'text'];

        return match ($action) {
            TemplateService::A_EN_CAMINO => [
                ['key' => 'hora_estimada', 'label' => 'Hora estimada de arribo (formato HH:MM, por ejemplo 14:30).', 'kind' => 'text'],
            ],
            TemplateService::A_EN_SITIO => [], // hora = ahora; sólo confirma
            TemplateService::A_REPROGRAMACION => [
                ['key' => 'nueva_fecha_hora', 'label' => 'Nueva fecha y hora de atención (por ejemplo 16/07/2026 10:00).', 'kind' => 'text'],
            ],
            TemplateService::A_DIAGNOSTICO => [
                ['key' => 'diagnostico', 'label' => 'Escribe el diagnóstico realizado. Puedes enviar varios mensajes y fotos; pulsa «Listo» al terminar.', 'kind' => 'multiline', 'ai' => true],
            ],
            TemplateService::A_PENDIENTE_CLIENTE => [
                ['key' => 'detalle', 'label' => 'Detalle del pendiente (breve).', 'kind' => 'text'],
            ],
            TemplateService::A_PENDIENTE_REFACCION => [
                ['key' => 'refacciones', 'label' => 'Lista de refacciones requeridas.', 'kind' => 'text'],
            ],
            TemplateService::A_RES_SIN_REFACCION => [$paso, $inicio, $termino, $vb],
            TemplateService::A_RES_CON_REFACCION => [
                $paso,
                ['key' => 'refacciones_utilizadas', 'label' => 'Refacciones utilizadas.', 'kind' => 'text'],
                $inicio, $termino, $vb,
            ],
            TemplateService::A_RES_REMOTA => [
                ['key' => 'modalidad', 'label' => 'Modalidad de atención remota:', 'kind' => 'choice',
                    'options' => ['Llamada telefonica', 'Conexion remota']],
                $paso, $inicio, $termino, $vb,
            ],
            TemplateService::A_RES_ARBITRARIA => [
                ['key' => 'motivo', 'label' => 'Motivo del cierre.', 'kind' => 'text'],
                ['key' => 'persona_solicita', 'label' => 'Persona que solicita el cierre.', 'kind' => 'text'],
            ],
            default => [],
        };
    }

    private function startFlow(array $link, int $chatId, string $action, array $context, int $ticketId): void
    {
        $meta = $this->templates->meta($action);
        if ($meta === null) {
            $this->telegram->sendMessage($chatId, 'Acción no reconocida.');
            return;
        }

        $flow = [
            'action' => $action,
            'ticket' => $context['ticket'] ?? null,
            'data'   => [],
            'photos' => [],
            'queue'  => $this->queueFor($action),
        ];

        $this->advance($link, $chatId, $flow);
    }

    /**
     * Prompts for queue[0] or, when the queue is empty, moves to confirmation.
     */
    private function advance(array $link, int $chatId, array $flow): void
    {
        $ticketId = (int) ($flow['ticket']['id'] ?? $this->currentTicketId($chatId));

        if (($flow['queue'] ?? []) === []) {
            $this->promptConfirm($link, $chatId, $flow);
            return;
        }

        $field = $flow['queue'][0];
        $this->states->saveState($chatId, self::S_IN_FLOW, $flow, $ticketId);

        if (($field['kind'] ?? 'text') === 'choice') {
            $rows = [];
            foreach ($field['options'] as $opt) {
                $rows[] = [['text' => $opt, 'callback_data' => 'choice:' . $opt]];
            }
            $this->telegram->sendMessage($chatId, $field['label'], $rows);
            return;
        }

        if (($field['kind'] ?? 'text') === 'multiline') {
            $this->telegram->sendMessage($chatId, $field['label'], [
                [['text' => 'Listo', 'callback_data' => 'done']],
            ]);
            return;
        }

        $this->telegram->sendMessage($chatId, $field['label']);
    }

    private function handleText(array $link, int $chatId, string $state, array $context, string $text): void
    {
        if ($state !== self::S_IN_FLOW || ($context['queue'] ?? []) === []) {
            $this->telegram->sendMessage($chatId, 'Usa /tickets para ver tus tickets y elegir una acción.');
            return;
        }
        if ($text === '') {
            return;
        }

        $field = $context['queue'][0];
        $kind  = $field['kind'] ?? 'text';

        if ($kind === 'choice') {
            $this->telegram->sendMessage($chatId, 'Por favor elige una de las opciones con los botones.');
            return;
        }

        if ($kind === 'multiline') {
            // Accumulate; the technician ends with the «Listo» button.
            $prev = trim((string) ($context['data'][$field['key']] ?? ''));
            $context['data'][$field['key']] = $prev === '' ? $text : $prev . "\n" . $text;
            $ticketId = (int) ($context['ticket']['id'] ?? $this->currentTicketId($chatId));
            $this->states->saveState($chatId, self::S_IN_FLOW, $context, $ticketId);
            return;
        }

        // Single-line text field: store and advance.
        $context['data'][$field['key']] = $text;
        array_shift($context['queue']);
        $this->advance($link, $chatId, $context);
    }

    private function handlePhoto(array $link, int $chatId, string $state, array $context, string $fileId): void
    {
        if ($state !== self::S_IN_FLOW || ($context['queue'][0]['kind'] ?? '') !== 'multiline' || $fileId === '') {
            $this->telegram->sendMessage($chatId, 'Solo puedo recibir fotos mientras documentas un diagnóstico o los pasos de una resolución.');
            return;
        }
        $context['photos'][] = $fileId;
        $ticketId = (int) ($context['ticket']['id'] ?? $this->currentTicketId($chatId));
        $this->states->saveState($chatId, self::S_IN_FLOW, $context, $ticketId);
        $this->telegram->sendMessage($chatId, 'Foto recibida (' . count($context['photos']) . '). Puedes enviar más o pulsar «Listo».');
    }

    private function applyChoice(array $link, int $chatId, array $context, string $value): void
    {
        $field = $context['queue'][0] ?? null;
        if ($field === null || ($field['kind'] ?? '') !== 'choice') {
            return;
        }
        if (! in_array($value, $field['options'], true)) {
            $this->telegram->sendMessage($chatId, 'Opción no válida.');
            return;
        }
        $context['data'][$field['key']] = $value;
        array_shift($context['queue']);
        $this->advance($link, $chatId, $context);
    }

    /**
     * Ends a multiline field: enforces the photo rule for solutions, optionally
     * runs AI formatting, then advances (or asks which text to use).
     */
    private function finishMultiline(array $link, int $chatId, array $context): void
    {
        $field = $context['queue'][0] ?? null;
        if ($field === null || ($field['kind'] ?? '') !== 'multiline') {
            return;
        }

        $raw = trim((string) ($context['data'][$field['key']] ?? ''));
        if ($raw === '') {
            $this->telegram->sendMessage($chatId, 'Aún no has escrito nada. Envía el texto y luego pulsa «Listo».');
            return;
        }

        $action = (string) $context['action'];
        $isSolution = ($this->templates->meta($action)['type'] ?? '') === TemplateService::TYPE_SOLUTION;
        if ($isSolution && $this->settings->requirePhotoOnResolution() && ($context['photos'] ?? []) === []) {
            $this->telegram->sendMessage($chatId, 'Se requiere al menos una foto para documentar la resolución. Envía una foto y luego pulsa «Listo».');
            return;
        }

        // AI formatting (optional, non-blocking).
        if (! empty($field['ai']) && $this->ai->isAvailable()) {
            $result = $this->ai->format($raw);

            // Log the call the moment it succeeds — tokens are spent regardless
            // of which text the technician ends up keeping.
            $usageId = null;
            if ($result['ok']) {
                $usageId = $this->aiUsage->record(
                    (int) $link['employee_id'],
                    (string) $result['model'],
                    (int) $result['input'],
                    (int) $result['output'],
                );
            }

            if ($result['ok'] && $result['formatted'] !== $raw) {
                $context['_fmt'] = [
                    'field'     => $field['key'],
                    'original'  => $raw,
                    'formatted' => $result['formatted'],
                    'tokens'    => $result['tokens'],
                    'usage_id'  => $usageId,
                ];
                $ticketId = (int) ($context['ticket']['id'] ?? $this->currentTicketId($chatId));
                $this->states->saveState($chatId, self::S_CHOOSE_FORMAT, $context, $ticketId);
                $this->telegram->sendMessage(
                    $chatId,
                    "Texto original:\n" . $this->shorten($raw, 1200)
                    . "\n\n———\n\nPropuesta formateada:\n" . $this->shorten($result['formatted'], 1200)
                    . "\n\n¿Cuál usamos?",
                    [
                        [['text' => 'Usar formateado', 'callback_data' => 'fmt:ai']],
                        [['text' => 'Usar original', 'callback_data' => 'fmt:orig']],
                    ]
                );
                return;
            }
        }

        // No AI: keep raw, advance.
        array_shift($context['queue']);
        $this->advance($link, $chatId, $context);
    }

    private function applyFormatChoice(array $link, int $chatId, array $context, string $choice): void
    {
        $fmt = $context['_fmt'] ?? null;
        if ($fmt === null) {
            return;
        }
        $context['data'][$fmt['field']] = $choice === 'ai' ? $fmt['formatted'] : $fmt['original'];
        $context['ai_used']    = $choice === 'ai';
        $context['ai_tokens']  = $choice === 'ai' ? (int) $fmt['tokens'] : (int) ($context['ai_tokens'] ?? 0) + (int) $fmt['tokens'];

        // Mark the usage row as accepted when the technician keeps the AI text.
        if ($choice === 'ai' && ! empty($fmt['usage_id'])) {
            $this->aiUsage->markAccepted((int) $fmt['usage_id']);
        }
        unset($context['_fmt']);
        array_shift($context['queue']);
        $this->advance($link, $chatId, $context);
    }

    // ------------------------------------------------------------------
    // Confirmation + execution
    // ------------------------------------------------------------------

    private function promptConfirm(array $link, int $chatId, array $flow): void
    {
        $ticketId = (int) ($flow['ticket']['id'] ?? $this->currentTicketId($chatId));
        $text     = $this->renderText($link, $flow, $ticketId);

        $meta = $this->templates->meta((string) $flow['action']);
        $note = match ($meta['type'] ?? '') {
            TemplateService::TYPE_PENDING  => "\n\nEsto dejará el ticket EN ESPERA (pausa el SLA).",
            TemplateService::TYPE_SOLUTION => "\n\nEsto marcará el ticket como RESUELTO.",
            default => '',
        };
        $photos = count($flow['photos'] ?? []);
        if ($photos > 0) {
            $note .= "\nSe adjuntarán {$photos} foto(s).";
        }

        $this->states->saveState($chatId, self::S_CONFIRM, $flow, $ticketId);
        $this->telegram->sendMessage(
            $chatId,
            "Revisa lo que se registrará en el ticket #{$ticketId}:\n\n" . $text . $note,
            [
                [['text' => 'Confirmar y registrar', 'callback_data' => 'confirm:yes']],
                [['text' => 'Cancelar', 'callback_data' => 'confirm:no']],
            ]
        );
    }

    private function renderText(array $link, array $flow, int $ticketId): string
    {
        $data = $flow['data'] ?? [];
        $data['_nombre_tecnico'] = $this->fullName($link);
        $data['_ticket_ref']     = '#' . $ticketId;
        $data['_now_hm']         = date('H:i');
        return $this->templates->render((string) $flow['action'], $data);
    }

    /**
     * Runs the action against GLPI: re-validates ownership + status, creates the
     * followup/solution, attaches photos, logs, and confirms to the technician.
     */
    private function execute(array $link, int $chatId, array $flow): void
    {
        $action   = (string) $flow['action'];
        $meta     = $this->templates->meta($action);
        $ticketId = (int) ($flow['ticket']['id'] ?? $this->currentTicketId($chatId));
        $glpiUser = (int) $link['glpi_user_id'];

        if ($meta === null || $ticketId <= 0) {
            $this->states->reset($chatId);
            $this->telegram->sendMessage($chatId, 'No se pudo determinar la acción. Usa /tickets para reiniciar.');
            return;
        }

        // Security + state gate: ownership and an actionable status.
        $ticket = $this->glpi->getTicket($ticketId);
        if ($ticket === null || ! $this->glpi->isAssignedTo($ticketId, $glpiUser)) {
            $this->states->reset($chatId);
            $this->telegram->sendMessage($chatId, 'Ya no puedes actuar sobre este ticket (no está asignado a ti o no está disponible).');
            return;
        }
        $statusBefore = (int) ($ticket['status'] ?? 0);
        if (in_array($statusBefore, [GlpiFieldService::STATUS_SOLVED, GlpiFieldService::STATUS_CLOSED], true)) {
            $this->states->reset($chatId);
            $this->telegram->sendMessage($chatId, 'Este ticket ya está resuelto o cerrado; no admite más acciones.');
            return;
        }

        $text = $this->renderText($link, $flow, $ticketId);

        // Create the followup/solution, attributed to the technician.
        $followupId = match ($meta['type']) {
            TemplateService::TYPE_FOLLOWUP => $this->glpi->addFollowup($ticketId, $text, true, $glpiUser),
            TemplateService::TYPE_PENDING  => $this->glpi->addFollowupAndWait($ticketId, $text, $glpiUser),
            TemplateService::TYPE_SOLUTION => $this->glpi->addSolution($ticketId, $text, $glpiUser),
            default => null,
        };

        if ($followupId === null) {
            $reason = $this->glpi->lastError ?: 'GLPI rechazó la operación.';
            $this->log($link, $chatId, $ticketId, $action, $meta, null, $statusBefore, $statusBefore, $flow, 'error', $reason);
            $this->states->reset($chatId);
            $this->telegram->sendMessage(
                $chatId,
                "No se pudo registrar la acción en GLPI.\nMotivo: " . $reason . "\n\nIntenta de nuevo o contacta a Mesa de Ayuda."
            );
            return;
        }

        // Attach photos (best-effort; failures do not roll back the followup).
        $attached = $this->attachPhotos($chatId, $ticketId, $flow['photos'] ?? []);

        $statusAfter = (int) $meta['status_after'];
        $this->log($link, $chatId, $ticketId, $action, $meta, $followupId, $statusBefore, $statusAfter, $flow, 'success', null);
        $this->states->reset($chatId);

        $confirm = match ($meta['type']) {
            TemplateService::TYPE_SOLUTION => "Ticket #{$ticketId} marcado como Resuelto.",
            TemplateService::TYPE_PENDING  => "Ticket #{$ticketId} puesto En espera. El SLA quedó en pausa.",
            default => "Registro añadido al ticket #{$ticketId}.",
        };
        if (($flow['photos'] ?? []) !== []) {
            $confirm .= " Fotos adjuntadas: {$attached}/" . count($flow['photos']) . '.';
        }
        $this->telegram->sendMessage($chatId, $confirm . "\n\nUsa /tickets para continuar.");
    }

    private function attachPhotos(int $chatId, int $ticketId, array $fileIds): int
    {
        $ok = 0;
        foreach ($fileIds as $i => $fileId) {
            $file = $this->telegram->getFile((string) $fileId);
            if (! $file['ok']) {
                continue;
            }
            $bytes = $this->telegram->downloadFile((string) $file['file_path']);
            if (! $bytes['ok']) {
                continue;
            }
            $ext  = pathinfo((string) $file['file_path'], PATHINFO_EXTENSION) ?: 'jpg';
            $name = 'foto_' . $ticketId . '_' . ($i + 1) . '.' . $ext;
            if ($this->glpi->attachPhotoToTicket($ticketId, $name, (string) $bytes['contents']) !== null) {
                $ok++;
            }
        }
        return $ok;
    }

    private function log(array $link, int $chatId, int $ticketId, string $action, array $meta, ?int $followupId, int $before, int $after, array $flow, string $result, ?string $error): void
    {
        // Payload for audit: the collected fields (never secrets) + rendered text.
        $payload = [
            'data'   => $flow['data'] ?? [],
            'photos' => count($flow['photos'] ?? []),
            'text'   => $this->renderText($link, $flow, $ticketId),
        ];

        $this->activity->record([
            'telegram_chat_id'   => $chatId,
            'employee_id'        => (int) $link['employee_id'],
            'glpi_ticket_id'     => $ticketId,
            'action'             => $action,
            'template_key'       => $meta['template_key'] ?? null,
            'glpi_followup_id'   => $followupId,
            'glpi_status_before' => $before,
            'glpi_status_after'  => $result === 'success' ? $after : $before,
            'payload'            => $payload,
            'ai_used'            => ! empty($flow['ai_used']) ? 1 : 0,
            'ai_tokens_used'     => (int) ($flow['ai_tokens'] ?? 0),
            'result'             => $result,
            'error_message'      => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function currentTicketId(int $chatId): ?int
    {
        return $this->states->loadState($chatId)['current_ticket_id'];
    }

    private function firstName(array $link): string
    {
        $n = trim((string) ($link['telegram_first_name'] ?? ''));
        return $n !== '' ? $n : 'técnico';
    }

    private function fullName(array $link): string
    {
        $name = trim(((string) ($link['employee_name'] ?? '')) . ' ' . ((string) ($link['employee_lastname'] ?? '')));
        return $name !== '' ? $name : $this->firstName($link);
    }

    private function shorten(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }
}
