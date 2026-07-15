<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use Anthropic\Client as AnthropicClient;
use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Models\ProvisioningExternalAccountModel;
use App\Modules\ServiceDesk\Models\ServiceDeskAiUsageModel;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;

/**
 * Self-service widget brain. An end user (not an operator) chats with the
 * assistant on the intranet; the assistant gathers the minimum to open ONE
 * ticket and, on confirmation, creates it SYNCHRONOUSLY in GLPI and returns the
 * ticket number.
 *
 * Business rules (fixed, not asked to the user):
 *   - status is always NUEVO (new);
 *   - the ITIL category is the one the SuperAdmin fixed for the widget;
 *   - opening date is "now";
 *   - requester name + location are appended to the ticket description;
 *   - when hardware is involved, equipo/modelo/serie (and an AI-inferred
 *     "categoria") are written to the configured plugin container fields.
 *
 * Unlike the operator AI creator, this never enqueues: it reuses
 * TicketBulkImporter::createOne() to file the single ticket on the spot.
 */
class WidgetTicketService
{
    /** Include a catalog as a JSON-schema enum only when the list is this small. */
    private const ENUM_LIMIT = 40;

    /** Options listed verbatim in the system prompt before summarizing the rest. */
    private const PROMPT_OPTION_LIMIT = 60;

    private const MAX_TOKENS = 2048;

    /** Business rule: the widget always opens tickets as "new". */
    private const FORCED_STATUS = 'NUEVO';

    /** Approx. USD per 1M tokens (input, output). */
    private const PRICES = [
        'claude-haiku-4-5' => ['in' => 1.0, 'out' => 5.0],
        'claude-sonnet-5'  => ['in' => 3.0, 'out' => 15.0],
        'claude-opus-4-8'  => ['in' => 5.0, 'out' => 25.0],
    ];

    /** @var array<string,mixed>|null|false hardware meta cache (false = not loaded). */
    private array|null|false $hwCache = false;

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
        private ServiceDeskSettings $settings,
        private TicketBulkImporter $importer,
        private ServiceDeskAiUsageModel $usage,
        private ServiceDeskImportModel $imports,
        private ProvisioningExternalAccountModel $externalAccounts,
    ) {}

    // ------------------------------------------------------------------
    // Bootstrap
    // ------------------------------------------------------------------

    /** Public config the embedded frame needs to render itself. */
    public function bootstrap(): array
    {
        return [
            'ready'   => $this->settings->widgetReady(),
            'title'   => $this->settings->widgetTitle(),
            'welcome' => $this->settings->widgetWelcome(),
        ];
    }

    // ------------------------------------------------------------------
    // Conversation
    // ------------------------------------------------------------------

    /**
     * One conversational turn. History is the running Anthropic message array,
     * kept by the caller (the frame holds it in memory). Returns reply text, an
     * optional question (ask_user), an optional confirmable draft (submit_ticket)
     * and the updated history.
     *
     * @param array  $history      prior messages (role/content arrays)
     * @param string $userMessage
     * @param array  $identity     known requester {nombre,correo,numero_empleado}
     *                             passed in by the host (intranet session). When
     *                             present the assistant no longer asks for them.
     * @return array{ok:bool,error?:string,reply?:string,question?:?array,draft?:?array,history?:array}
     */
    public function chat(array $history, string $userMessage, array $identity = []): array
    {
        if (! $this->settings->widgetReady()) {
            return ['ok' => false, 'error' => 'El asistente no está disponible en este momento.'];
        }

        $budget = $this->settings->aiDailyTokenBudget();
        if ($budget > 0 && $this->usage->tokensToday() >= $budget) {
            return ['ok' => false, 'error' => 'El asistente alcanzó su límite diario. Intenta más tarde.'];
        }

        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            return ['ok' => false, 'error' => 'Escribe tu mensaje.'];
        }

        $hw        = $this->hardwareMeta();
        $idn       = $this->sanitizeIdentity($identity);
        $history[] = ['role' => 'user', 'content' => $userMessage];

        $model = $this->settings->aiModel();
        try {
            $client  = new AnthropicClient(apiKey: $this->settings->aiApiKey());
            $message = $client->messages->create(
                maxTokens: self::MAX_TOKENS,
                model: $model,
                system: [[
                    'type'         => 'text',
                    'text'         => $this->systemPrompt($hw, $idn),
                    'cacheControl' => ['type' => 'ephemeral'],
                ]],
                messages: $history,
                tools: [$this->askTool(), $this->submitTool($hw, $idn)],
            );
        } catch (\Throwable $e) {
            log_message('error', '[ServiceDesk][widget] chat failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo contactar al asistente. Intenta de nuevo en un momento.'];
        }

        $assistantContent = [];
        $replyText        = '';
        $submitId         = null;
        $submitInput      = null;
        $askId            = null;
        $askInput         = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $assistantContent[] = ['type' => 'text', 'text' => $block->text];
                $replyText .= $block->text;
            } elseif ($block->type === 'tool_use') {
                $assistantContent[] = ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $block->input];
                if ($block->name === 'submit_ticket') {
                    $submitId    = $block->id;
                    $submitInput = $block->input;
                } elseif ($block->name === 'ask_user') {
                    $askId    = $block->id;
                    $askInput = $block->input;
                }
            }
        }
        $history[] = ['role' => 'assistant', 'content' => $assistantContent];

        $draft    = null;
        $question = null;

        if ($submitId !== null) {
            $draft     = $this->normalizeDraft(is_array($submitInput) ? $submitInput : [], $hw);
            // Show the known requester identity on the confirmation card; the
            // ticket writer takes these from $identity, not from the AI.
            if ($idn['nombre'] !== '') {
                $draft['solicitante'] = $idn['nombre'];
            }
            $draft['correo']          = $idn['correo'];
            $draft['numero_empleado'] = $idn['numero_empleado'];
            $history[] = $this->toolResult($submitId, 'El resumen del ticket se mostró al usuario para su confirmación. Espera a que confirme o pida cambios; no vuelvas a llamar a submit_ticket a menos que cambien los datos.');
            if (trim($replyText) === '') {
                $replyText = 'Con estos datos puedo crear tu ticket. Revísalos y confírmame para levantarlo.';
            }
        } elseif ($askId !== null) {
            $question  = $this->buildQuestion(is_array($askInput) ? $askInput : []);
            $history[] = $this->toolResult($askId, 'La pregunta se mostró al usuario; espera su respuesta.');
            if ($question['text'] !== '') {
                $replyText = $question['text'];
            }
        }

        $this->logUsage('chat', $model, $message->usage, 0);

        return [
            'ok'       => true,
            'reply'    => trim($replyText),
            'question' => $question,
            'draft'    => $draft,
            'history'  => $history,
        ];
    }

    // ------------------------------------------------------------------
    // Create: confirmed draft -> single GLPI ticket (synchronous)
    // ------------------------------------------------------------------

    /**
     * Creates the ticket from a confirmed draft and returns its GLPI id in
     * data['ticketId']. Re-validates the draft server-side (the endpoint is
     * public), enforces the widget business rules, and delegates the actual
     * GLPI write to TicketBulkImporter::createOne().
     *
     * @param array<string,mixed> $draft
     * @param array<string,mixed> $identity known requester {nombre,correo,numero_empleado}
     */
    public function createTicket(array $draft, array $identity = []): ServiceResult
    {
        if (! $this->settings->widgetReady()) {
            return ServiceResult::fail('El asistente no está disponible en este momento.');
        }

        $hw  = $this->hardwareMeta();
        $d   = $this->normalizeDraft($draft, $hw);
        $idn = $this->sanitizeIdentity($identity);

        // The requester name is authoritative from the host identity; only fall
        // back to whatever the AI captured when the host did not pass one.
        $solicitante = $idn['nombre'] !== '' ? $idn['nombre'] : $d['solicitante'];

        $missing = [];
        foreach (['titulo' => 'un título', 'descripcion' => 'la descripción', 'ubicacion' => 'tu ubicación'] as $k => $label) {
            if ($d[$k] === '') {
                $missing[] = $label;
            }
        }
        if ($solicitante === '') {
            $missing[] = 'tu nombre';
        }
        if ($missing !== []) {
            return ServiceResult::fail('Faltan datos para crear el ticket: ' . implode(', ', $missing) . '.');
        }
        if ($d['tipo'] === '') {
            // The AI infers it; never block the user on this — default to a fault.
            $d['tipo'] = 'INCIDENCIA';
        }

        // Base headers are container-independent, so an empty plan resolves them.
        $baseHeader = [];
        foreach ($this->introspector->buildPlan([], false)['columns'] as $c) {
            if (($c['kind'] ?? '') === 'base') {
                $baseHeader[$c['glpiKey']] = $c['header'];
            }
        }

        $idLines   = ['Solicitante: ' . $solicitante];
        if ($idn['correo'] !== '') {
            $idLines[] = 'Correo: ' . $idn['correo'];
        }
        if ($idn['numero_empleado'] !== '') {
            $idLines[] = 'No. de empleado: ' . $idn['numero_empleado'];
        }
        $idLines[] = 'Ubicación: ' . $d['ubicacion'];

        $content = $d['descripcion'] . "\n\n----------\n" . implode("\n", $idLines);

        $row = [
            $baseHeader['name']    => $d['titulo'],
            $baseHeader['content'] => $content,
            $baseHeader['type']    => $d['tipo'],
            $baseHeader['status']  => self::FORCED_STATUS,
            $baseHeader['date']    => date('Y-m-d H:i:s'),
        ];

        $catName = $this->categoryName();
        if ($catName !== '' && isset($baseHeader['itilcategories_id'])) {
            $row[$baseHeader['itilcategories_id']] = $catName;
        }

        // Hardware -> real plugin container fields (only when there is something to write).
        $containerIds = [];
        if ($hw !== null && $d['es_hardware']
            && ($d['equipo'] !== '' || $d['modelo'] !== '' || $d['serie'] !== '' || $d['categoria'] !== '')) {
            $containerIds = [$hw['containerId']];
            foreach (['equipo', 'modelo', 'serie', 'categoria'] as $role) {
                if (isset($hw[$role]) && $d[$role] !== '') {
                    $row[$hw[$role]['header']] = $d[$role];
                }
            }
        }

        // Requester: if the employee number maps to an active GLPI user in
        // Provisioning, file the ticket under that user so it shows up as their
        // own in GLPI. Otherwise fall back to the panel-configured requester.
        $requester = $this->settings->widgetRequesterUserId();
        if ($idn['numero_empleado'] !== '') {
            $glpiUserId = $this->externalAccounts->glpiUserIdByEmployeeNumber($idn['numero_empleado']);
            if ($glpiUserId !== null) {
                $requester = $glpiUserId;
            }
        }

        $res = $this->importer->createOne($containerIds, $row, [
            'requester'       => $requester,
            'entities'        => $this->settings->widgetEntitiesId(),
            'requesttypes_id' => $this->settings->widgetRequestSourceId(),
        ]);
        if (! $res->success) {
            return $res;
        }

        $ticketId = (int) ($res->data['ticketId'] ?? 0);
        $this->recordImport($d['titulo'], $ticketId);
        $this->logUsage('create', $this->settings->aiModel(), null, 1);

        return ServiceResult::ok(['ticketId' => $ticketId], "Tu ticket #{$ticketId} se creó correctamente.");
    }

    // ------------------------------------------------------------------
    // Draft normalization + question
    // ------------------------------------------------------------------

    /**
     * Coerces a submit_ticket / posted draft into a clean, validated shape.
     * Hardware fields are only kept when hardware capture is configured.
     *
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    private function normalizeDraft(array $in, ?array $hw): array
    {
        $get  = static fn(string $k): string => trim((string) ($in[$k] ?? ''));
        $tipo = mb_strtoupper($get('tipo'));
        if (! in_array($tipo, ['INCIDENCIA', 'REQUERIMIENTO'], true)) {
            $tipo = '';
        }

        $d = [
            'titulo'      => mb_substr($get('titulo'), 0, 255),
            'descripcion' => $get('descripcion'),
            'tipo'        => $tipo,
            'solicitante' => mb_substr($get('solicitante'), 0, 190),
            'ubicacion'   => mb_substr($get('ubicacion'), 0, 190),
            'es_hardware' => ! empty($in['es_hardware']) && $hw !== null,
            'equipo'      => '',
            'modelo'      => '',
            'serie'       => '',
            'categoria'   => '',
        ];

        if ($hw !== null && $d['es_hardware']) {
            $d['equipo']    = isset($hw['equipo'])    ? $get('equipo')    : '';
            $d['modelo']    = isset($hw['modelo'])    ? $get('modelo')    : '';
            $d['serie']     = isset($hw['serie'])     ? $get('serie')     : '';
            $d['categoria'] = isset($hw['categoria']) ? $get('categoria') : '';
        }

        return $d;
    }

    /**
     * Normalizes the host-supplied requester identity (intranet session data).
     * These fields are authoritative and are never taken from the AI.
     *
     * @param array<string,mixed> $in
     * @return array{nombre:string,correo:string,numero_empleado:string}
     */
    private function sanitizeIdentity(array $in): array
    {
        $clean = static fn(string $k, int $max): string => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);

        return [
            'nombre'          => $clean('nombre', 190),
            'correo'          => $clean('correo', 190),
            'numero_empleado' => $clean('numero_empleado', 40),
        ];
    }

    /**
     * @param array<string,mixed> $in
     * @return array{text:string,options:string[],allowFreeText:bool}
     */
    private function buildQuestion(array $in): array
    {
        $opts = array_values(array_filter(array_map(
            static fn($o) => trim((string) $o),
            is_array($in['options'] ?? null) ? $in['options'] : [],
        ), static fn($o) => $o !== ''));

        return [
            'text'          => trim((string) ($in['question'] ?? '')),
            'options'       => $opts,
            'allowFreeText' => (bool) ($in['allow_free_text'] ?? ($opts === [])),
        ];
    }

    // ------------------------------------------------------------------
    // Prompt + tools
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed>|null $hw
     * @param array{nombre:string,correo:string,numero_empleado:string} $idn
     */
    private function systemPrompt(?array $hw, array $idn = ['nombre' => '', 'correo' => '', 'numero_empleado' => '']): string
    {
        $knowsRequester = $idn['nombre'] !== '';

        $lines   = [];
        $lines[] = $this->settings->widgetSystemPrompt();
        $lines[] = '';

        if ($knowsRequester) {
            $lines[] = 'SOLICITANTE (ya identificado, NO lo preguntes):';
            $lines[] = '- Nombre: ' . $idn['nombre'];
            if ($idn['correo'] !== '') {
                $lines[] = '- Correo: ' . $idn['correo'];
            }
            if ($idn['numero_empleado'] !== '') {
                $lines[] = '- Número de empleado: ' . $idn['numero_empleado'];
            }
            $lines[] = 'Salúdalo por su nombre. Ya tienes su nombre, correo y número de empleado: NO los preguntes ni los pidas de nuevo; el sistema los adjunta al ticket automáticamente.';
            $lines[] = '';
        }

        $lines[] = 'CÓMO TRABAJAS:';
        $lines[] = $knowsRequester
            ? '1. Tu meta es reunir lo mínimo para levantar UN ticket: un título breve (descripción corta de la falla o solicitud), una descripción detallada y la ubicación de la persona.'
            : '1. Tu meta es reunir lo mínimo para levantar UN ticket: un título breve (descripción corta de la falla o solicitud), una descripción detallada, el nombre de quien reporta y su ubicación.';
        $lines[] = '2. Determina tú el TIPO: usa INCIDENCIA cuando algo falla o dejó de funcionar, y REQUERIMIENTO cuando es una petición o solicitud. Solo pregunta si de verdad no puedes deducirlo.';
        $lines[] = '3. Haz UNA sola pregunta a la vez con la herramienta ask_user. Para respuestas de pocas opciones, pásalas en "options" para elegir con un clic.';
        if ($hw !== null) {
            $lines[] = '4. Si el reporte involucra un equipo físico (computadora, impresora, teléfono, etc.), marca es_hardware y, de forma OPCIONAL y sin obligar, pide el equipo, el modelo y el número de serie. Si la persona no los tiene a la mano, continúa sin ellos.';
        }
        $lines[] = $knowsRequester
            ? '5. Cuando tengas título, descripción y ubicación, llama a submit_ticket con todos los datos. No describas el ticket en un mensaje de texto: pásalo por la herramienta para que la persona lo confirme.'
            : '5. Cuando tengas título, descripción, nombre y ubicación, llama a submit_ticket con todos los datos. No describas el ticket en un mensaje de texto: pásalo por la herramienta para que la persona lo confirme.';
        $lines[] = '';
        $lines[] = 'REGLAS ESTRICTAS:';
        $lines[] = '- No preguntes por la categoría, el estatus, la fecha ni la prioridad: el sistema los asigna automáticamente.';
        $lines[] = '- No inventes datos. Si falta algo requerido, pregúntalo antes de llamar a submit_ticket.';
        $lines[] = '- Mantente SIEMPRE en tu propósito de soporte. Si el mensaje no trata de una falla o una solicitud, declina en una frase y pide el reporte. Sin emojis ni encabezados Markdown.';

        if ($hw !== null && ! empty($hw['equipo']['options'])) {
            $opts  = $hw['equipo']['options'];
            $count = count($opts);
            $shown = implode(' | ', array_slice($opts, 0, self::PROMPT_OPTION_LIMIT));
            $suffix = $count > self::PROMPT_OPTION_LIMIT
                ? ", hay {$count} en total, aquí los primeros " . self::PROMPT_OPTION_LIMIT
                : '';
            $lines[] = '';
            $lines[] = "TIPOS DE EQUIPO válidos (si aplica, usa EXACTAMENTE uno en el campo equipo{$suffix}): {$shown}";
        }

        return implode("\n", $lines);
    }

    /** A user-facing tool_result turn (keeps the history valid after a tool_use). */
    private function toolResult(string $toolUseId, string $content): array
    {
        return [
            'role'    => 'user',
            'content' => [[
                'type'      => 'tool_result',
                'toolUseID' => $toolUseId,
                'content'   => $content,
            ]],
        ];
    }

    private function askTool(): array
    {
        return [
            'name'        => 'ask_user',
            'description' => 'Haz UNA sola pregunta a la persona para reunir lo que falta. Para elegir de un conjunto corto (sí/no, pocas opciones) pásalas en "options" para elegir con un clic; para texto libre déjalo vacío.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'question'        => ['type' => 'string', 'description' => 'La pregunta, breve y clara. Solo una pregunta.'],
                    'options'         => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Opciones para elegir con un clic, solo para listas cortas.'],
                    'allow_free_text' => ['type' => 'boolean', 'description' => 'true si, además de las opciones, la persona puede escribir su propia respuesta.'],
                ],
                'required'   => ['question'],
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $hw
     * @param array{nombre:string,correo:string,numero_empleado:string} $idn
     */
    private function submitTool(?array $hw, array $idn = ['nombre' => '', 'correo' => '', 'numero_empleado' => '']): array
    {
        $knowsRequester = $idn['nombre'] !== '';

        $props = [
            'titulo'      => ['type' => 'string', 'description' => 'Requerido. Título o descripción breve de la falla o solicitud.'],
            'descripcion' => ['type' => 'string', 'description' => 'Requerido. Descripción detallada de lo que ocurre o se solicita.'],
            'tipo'        => ['type' => 'string', 'enum' => ['INCIDENCIA', 'REQUERIMIENTO'], 'description' => 'Requerido. INCIDENCIA si algo falla; REQUERIMIENTO si es una petición o solicitud.'],
            'ubicacion'   => ['type' => 'string', 'description' => 'Requerido. Ubicación de la persona (sitio, área o sucursal).'],
        ];
        $required = ['titulo', 'descripcion', 'tipo', 'ubicacion'];

        // Only ask the AI for the requester name when the host did not identify them.
        if (! $knowsRequester) {
            $props['solicitante'] = ['type' => 'string', 'description' => 'Requerido. Nombre completo de la persona que reporta.'];
            $required[]           = 'solicitante';
        }

        if ($hw !== null) {
            $props['es_hardware'] = ['type' => 'boolean', 'description' => 'true si el reporte involucra un equipo físico.'];

            $equipo = ['type' => 'string', 'description' => 'Opcional. Tipo de equipo, solo si es_hardware.'];
            if (! empty($hw['equipo']['options']) && count($hw['equipo']['options']) <= self::ENUM_LIMIT) {
                $equipo['enum'] = array_values($hw['equipo']['options']);
            }
            $props['equipo'] = $equipo;

            if (isset($hw['modelo'])) {
                $props['modelo'] = ['type' => 'string', 'description' => 'Opcional. Modelo del equipo.'];
            }
            if (isset($hw['serie'])) {
                $props['serie'] = ['type' => 'string', 'description' => 'Opcional. Número de serie del equipo.'];
            }
            if (isset($hw['categoria'])) {
                $cat = ['type' => 'string', 'description' => 'Opcional. Clasificación del equipo; dedúcela del contexto si es posible, si no la dejas vacía.'];
                if (! empty($hw['categoria']['options']) && count($hw['categoria']['options']) <= self::ENUM_LIMIT) {
                    $cat['enum'] = array_values($hw['categoria']['options']);
                }
                $props['categoria'] = $cat;
            }
        }

        return [
            'name'        => 'submit_ticket',
            'description' => 'Registra el ticket para que la persona lo confirme antes de crearlo. Úsala solo cuando tengas al menos el título, la descripción, el nombre y la ubicación.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => $props,
                'required'   => $required,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Config resolution
    // ------------------------------------------------------------------

    /**
     * Resolves the hardware plugin container into a role->column map the AI and
     * the writer both use. Returns null when hardware capture is not configured
     * or the equipo field cannot be resolved from the live schema.
     *
     * @return array<string,mixed>|null
     */
    private function hardwareMeta(): ?array
    {
        if ($this->hwCache !== false) {
            return $this->hwCache;
        }
        if (! $this->settings->widgetHardwareConfigured()) {
            return $this->hwCache = null;
        }

        $cid  = $this->settings->widgetContainerId();
        $cols = $this->introspector->buildPlan([$cid], true)['columns'];

        $byField = [];
        foreach ($cols as $c) {
            if (($c['kind'] ?? '') === 'plugin' && (int) ($c['containerId'] ?? 0) === $cid) {
                $byField[$c['field']] = $c;
            }
        }

        $roles = [
            'equipo'    => $this->settings->widgetFieldEquipo(),
            'modelo'    => $this->settings->widgetFieldModelo(),
            'serie'     => $this->settings->widgetFieldSerie(),
            'categoria' => $this->settings->widgetFieldCategoria(),
        ];

        $meta = ['containerId' => $cid];
        foreach ($roles as $role => $fieldName) {
            if ($fieldName !== '' && isset($byField[$fieldName])) {
                $meta[$role] = [
                    'header'  => $byField[$fieldName]['header'],
                    'options' => array_values($byField[$fieldName]['options'] ?? []),
                    'type'    => $byField[$fieldName]['type'],
                ];
            }
        }

        // The equipo field anchors hardware capture; without it, disable it.
        if (! isset($meta['equipo'])) {
            return $this->hwCache = null;
        }

        return $this->hwCache = $meta;
    }

    private function categoryName(): string
    {
        $id = $this->settings->widgetItilCategoryId();
        if ($id <= 0) {
            return '';
        }
        foreach ($this->introspector->categories() as $c) {
            if ((int) $c['id'] === $id) {
                return (string) $c['name'];
            }
        }
        return '';
    }

    // ------------------------------------------------------------------
    // Bookkeeping
    // ------------------------------------------------------------------

    /** Leaves a trace row in servicedesk_imports for the history screen. */
    private function recordImport(string $titulo, int $ticketId): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $this->imports->insert([
                'name'            => 'Widget: ' . mb_substr($titulo, 0, 110) . ($ticketId > 0 ? " (Ticket #{$ticketId})" : ''),
                'source_filename' => 'widget',
                'status'          => 'ready',
                'source'          => 'widget',
                'total_rows'      => 1,
                'processed_rows'  => 1,
                'succeeded_rows'  => 1,
                'failed_rows'     => 0,
                'uploaded_by'     => null,
                'started_at'      => $now,
                'finished_at'     => $now,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[ServiceDesk][widget] import trace failed: ' . $e->getMessage());
        }
    }

    private function logUsage(string $kind, string $model, $usage, int $created): void
    {
        $in = $usage ? (int) ($usage->inputTokens ?? 0) : 0;
        $out = $usage ? (int) ($usage->outputTokens ?? 0) : 0;
        $cr  = $usage ? (int) ($usage->cacheReadInputTokens ?? 0) : 0;
        $cw  = $usage ? (int) ($usage->cacheCreationInputTokens ?? 0) : 0;

        try {
            $this->usage->insert([
                'user_id'            => null,
                'kind'               => $kind,
                'model'              => $model,
                'input_tokens'       => $in,
                'output_tokens'      => $out,
                'cache_read_tokens'  => $cr,
                'cache_write_tokens' => $cw,
                'tickets_proposed'   => 0,
                'tickets_created'    => $created,
                'estimated_cost'     => $this->estimateCost($model, $in, $out, $cr, $cw),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[ServiceDesk][widget] usage log failed: ' . $e->getMessage());
        }
    }

    private function estimateCost(string $model, int $in, int $out, int $cacheRead, int $cacheWrite): float
    {
        $p = self::PRICES[$model] ?? ['in' => 1.0, 'out' => 5.0];
        return round(
            ($in / 1_000_000) * $p['in']
            + ($out / 1_000_000) * $p['out']
            + ($cacheRead / 1_000_000) * $p['in'] * 0.1
            + ($cacheWrite / 1_000_000) * $p['in'] * 1.25,
            6,
        );
    }
}
