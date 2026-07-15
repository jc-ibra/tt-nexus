<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use Anthropic\Client as AnthropicClient;
use App\Modules\Core\Services\ServiceResult;
use App\Modules\ServiceDesk\Models\ServiceDeskAiUsageModel;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * AI-assisted ticket creator. Turns a natural-language conversation into a
 * structured list of ticket rows that the operator reviews, edits and then
 * submits through the SAME validated import pipeline as the bulk importer.
 *
 * Design rule: the model NEVER touches GLPI. It only proposes rows (via the
 * propose_tickets tool). Grounding against catalogs comes from the live import
 * plan (GlpiSchemaIntrospector::buildPlan): dropdown/enum columns are offered as
 * enums in the tool schema and listed in the system prompt, and everything is
 * re-validated server-side by TicketImportValidator before enqueuing.
 */
class TicketCreatorService
{
    /** Include catalog values as a JSON-schema enum only when the list is this small. */
    private const ENUM_LIMIT = 40;

    /** Options listed verbatim in the system prompt before summarizing the rest. */
    private const PROMPT_OPTION_LIMIT = 60;

    /** Above this many options, ask_user renders a search box instead of chips. */
    private const AUTOCOMPLETE_THRESHOLD = 35;

    private const MAX_TOKENS = 4096;

    /**
     * Business rule: the AI creator only ever opens tickets "in progress".
     * The status field is locked to this value and the assistant is told not to
     * ask about it (must match a label in ServiceDesk::$ticketStatuses).
     */
    private const FORCED_STATUS = 'EN CURSO';

    /** Approx. USD per 1M tokens (input, output). */
    private const PRICES = [
        'claude-haiku-4-5' => ['in' => 1.0, 'out' => 5.0],
        'claude-sonnet-5'  => ['in' => 3.0, 'out' => 15.0],
        'claude-opus-4-8'  => ['in' => 5.0, 'out' => 25.0],
    ];

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
        private ServiceDeskSettings $settings,
        private TicketImportValidator $validator,
        private ServiceDeskAiUsageModel $usage,
        private ServiceDeskImportModel $imports,
    ) {}

    // ------------------------------------------------------------------
    // Grid schema
    // ------------------------------------------------------------------

    /**
     * Editable-grid column metadata for a container selection: header, type,
     * required flag and option list. Excludes the output (TICKET_ID) column.
     *
     * @param int[] $containerIds
     * @return array<int,array{header:string,type:string,required:bool,options:string[],kind:string}>
     */
    public function columns(array $containerIds): array
    {
        $plan = $this->introspector->buildPlan($containerIds, true);
        $out  = [];
        foreach ($plan['columns'] as $col) {
            if (($col['kind'] ?? '') === 'output') {
                continue;
            }
            $glpiKey = $col['glpiKey'] ?? null;
            $options = array_values($col['options'] ?? []);
            // Lock the status column to the single allowed value.
            if ($glpiKey === 'status') {
                $options = [self::FORCED_STATUS];
            }
            // The creator only opens tickets "in progress", so a close date is
            // never applicable: hide it from the grid, the tool and the prompt,
            // but keep it in the column set so the workbook still carries the
            // header the importer's validator expects (written empty).
            $hidden = ($glpiKey === 'closedate');
            $out[] = [
                'header'   => $col['header'],
                'glpiKey'  => $glpiKey,
                'type'     => $col['type'],
                'required' => ! empty($col['required']) && ! $hidden,
                'locked'   => $glpiKey === 'status',
                'hidden'   => $hidden,
                'options'  => $options,
                'kind'     => $col['kind'],
            ];
        }
        return $out;
    }

    /** Header of the status column (glpiKey 'status'), if present. */
    private function statusHeader(array $columns): ?string
    {
        foreach ($columns as $c) {
            if (($c['glpiKey'] ?? null) === 'status') {
                return $c['header'];
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Conversation
    // ------------------------------------------------------------------

    /**
     * Runs one conversational turn. History is the running Anthropic message
     * array (role/content), managed by the caller (kept in the PHP session for
     * an ephemeral chat). Returns reply text, an optional ticket proposal, the
     * updated history and the grid columns.
     *
     * @param int[]  $containerIds
     * @param array  $history        prior messages (role/content arrays)
     * @param string $userMessage
     * @return array{ok:bool,error?:string,reply?:string,proposal?:array,columns?:array,history?:array}
     */
    public function chat(array $containerIds, array $history, string $userMessage, ?int $userId): array
    {
        if (! $this->settings->aiReady()) {
            return ['ok' => false, 'error' => 'El creador con IA no está configurado. Pide al administrador que lo active con una API key.'];
        }
        if ($containerIds === []) {
            return ['ok' => false, 'error' => 'Selecciona primero el tipo de ticket (contenedor) sobre el que quieres trabajar.'];
        }

        $budget = $this->settings->aiDailyTokenBudget();
        if ($budget > 0 && $this->usage->tokensToday() >= $budget) {
            return ['ok' => false, 'error' => 'Se alcanzó el presupuesto diario de tokens de IA. Intenta de nuevo mañana o pide ampliarlo.'];
        }

        $columns = $this->columns($containerIds);
        if ($columns === []) {
            return ['ok' => false, 'error' => 'No se pudieron leer los campos del contenedor seleccionado.'];
        }

        $history[] = ['role' => 'user', 'content' => trim($userMessage)];

        $model = $this->settings->aiModel();
        try {
            $client  = new AnthropicClient(apiKey: $this->settings->aiApiKey());
            $message = $client->messages->create(
                maxTokens: self::MAX_TOKENS,
                model: $model,
                system: [[
                    'type'         => 'text',
                    'text'         => $this->systemPrompt($columns),
                    'cacheControl' => ['type' => 'ephemeral'],
                ]],
                messages: $history,
                tools: [$this->askTool(), $this->proposeTool($columns)],
            );
        } catch (\Throwable $e) {
            log_message('error', '[ServiceDesk][AI] chat failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo contactar al servicio de IA: ' . $e->getMessage()];
        }

        // Reconstruct the assistant turn as plain arrays so history round-trips.
        $assistantContent = [];
        $replyText        = '';
        $proposalId       = null;
        $proposalRaw      = null;
        $askId            = null;
        $askInput         = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $assistantContent[] = ['type' => 'text', 'text' => $block->text];
                $replyText .= $block->text;
            } elseif ($block->type === 'tool_use') {
                $assistantContent[] = ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $block->input];
                if ($block->name === 'propose_tickets') {
                    $proposalId  = $block->id;
                    $proposalRaw = $block->input['tickets'] ?? [];
                } elseif ($block->name === 'ask_user') {
                    $askId    = $block->id;
                    $askInput = $block->input;
                }
            }
        }
        $history[] = ['role' => 'assistant', 'content' => $assistantContent];

        $proposal = null;
        $question = null;

        if ($proposalId !== null) {
            $proposal  = $this->normalizeProposal(is_array($proposalRaw) ? $proposalRaw : [], $columns);
            $history[] = $this->toolResult($proposalId, 'La propuesta se mostró al usuario en una tabla editable. Espera sus ajustes o su confirmación para crear.');
            if (trim($replyText) === '') {
                $replyText = 'Preparé una propuesta de ' . count($proposal) . ' ticket(s). Revísala y ajústala en la tabla; cuando estés conforme, créalos.';
            }
        } elseif ($askId !== null) {
            $question  = $this->buildQuestion(is_array($askInput) ? $askInput : [], $columns);
            $history[] = $this->toolResult($askId, 'La pregunta se mostró al usuario; espera su respuesta.');
            if ($question['text'] !== '') {
                $replyText = $question['text']; // the question is the message shown
            }
        }

        $this->logUsage($userId, 'chat', $model, $message->usage, $proposal !== null ? count($proposal) : 0, 0);

        return [
            'ok'       => true,
            'reply'    => trim($replyText),
            'proposal' => $proposal,
            'question' => $question,
            'columns'  => $columns,
            'history'  => $history,
        ];
    }

    // ------------------------------------------------------------------
    // Create: rows -> validated import job
    // ------------------------------------------------------------------

    /**
     * Builds an import workbook from the edited rows and enqueues it exactly
     * like an uploaded template (same validator, same servicedesk_imports job,
     * same worker). Returns the import id in data['importId'] on success.
     *
     * @param int[]                       $containerIds
     * @param array<int,array<string,mixed>> $rows keyed by column header
     */
    public function enqueueFromRows(array $containerIds, array $rows, string $name, ?int $userId): ServiceResult
    {
        if (! $this->settings->importEnabled()) {
            return ServiceResult::fail('La creación de tickets está deshabilitada por el administrador.');
        }
        if ($containerIds === []) {
            return ServiceResult::fail('No se indicó el tipo de ticket (contenedor).');
        }
        $name = trim($name);
        if ($name === '') {
            return ServiceResult::fail('Indica un nombre para el lote: sirve para identificar la creación.');
        }
        $rows = array_values(array_filter($rows, static fn($r) => is_array($r) && array_filter($r, static fn($v) => trim((string) $v) !== '') !== []));
        if ($rows === []) {
            return ServiceResult::fail('No hay filas con datos para crear.');
        }

        $max = $this->settings->aiMaxTicketsPerRequest();
        if (count($rows) > $max) {
            return ServiceResult::fail("La propuesta tiene " . count($rows) . " tickets y el máximo por solicitud es {$max}.");
        }

        $columns      = $this->columns($containerIds);
        $headers      = array_map(static fn($c) => $c['header'], $columns);
        $statusHeader = $this->statusHeader($columns);

        // Enforce the "in progress only" rule on every row before writing.
        if ($statusHeader !== null) {
            foreach ($rows as &$row) {
                $row[$statusHeader] = self::FORCED_STATUS;
            }
            unset($row);
        }

        $tmpDir = WRITEPATH . 'servicedesk/tmp';
        $this->ensureDir($tmpDir);
        $tmpPath = $tmpDir . '/ai_' . bin2hex(random_bytes(6)) . '.xlsx';

        try {
            $this->writeWorkbook($tmpPath, $containerIds, $headers, $rows);
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            log_message('error', '[ServiceDesk][AI] workbook build failed: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo preparar el archivo de creación: ' . $e->getMessage());
        }

        // Same validation gate the manual upload uses.
        $validation = $this->validator->validateFile($tmpPath);
        if (! $validation->success) {
            @unlink($tmpPath);
            return ServiceResult::fail($validation->errors, $validation->data);
        }

        // Create the job, then move the file into its own folder (mirrors upload()).
        $importId = $this->imports->insert([
            'name'            => $name,
            'source_filename' => 'creador_ia.xlsx',
            'status'          => 'pending',
            'source'          => 'ai_creator',
            'total_rows'      => (int) ($validation->data['totalRows'] ?? count($rows)),
            'uploaded_by'     => $userId,
        ], true);

        $jobDir = WRITEPATH . 'servicedesk/uploads/' . $importId;
        $this->ensureDir($jobDir);
        $sourcePath = $jobDir . '/source.xlsx';
        rename($tmpPath, $sourcePath);

        $logDir = WRITEPATH . 'servicedesk/logs';
        $this->ensureDir($logDir);

        $this->imports->update($importId, [
            'source_path' => $sourcePath,
            'log_path'    => $logDir . '/import_' . $importId . '.log',
        ]);

        $this->logUsage($userId, 'create', $this->settings->aiModel(), null, 0, count($rows));

        $warn = count($validation->data['warnings'] ?? []);
        $msg  = "Creación #{$importId} encolada: " . count($rows) . ' ticket(s).'
            . ($warn > 0 ? " {$warn} aviso(s) (valores que se crearán)." : '');

        return ServiceResult::ok(['importId' => $importId, 'warnings' => $validation->data['warnings'] ?? []], $msg);
    }

    // ------------------------------------------------------------------
    // Prompt + tool schema
    // ------------------------------------------------------------------

    private function systemPrompt(array $columns): string
    {
        $max   = $this->settings->aiMaxTicketsPerRequest();
        $lines = [];
        // Admin-editable persona / scope / style (grounding rules are appended below by code).
        $lines[] = $this->settings->aiSystemPrompt();
        $lines[] = '';
        $lines[] = 'CÓMO TRABAJAS:';
        $lines[] = '1. Reúne la información haciendo UNA sola pregunta a la vez con la herramienta ask_user. Nunca escribas listas largas de preguntas en un mensaje: resulta tedioso.';
        $lines[] = '2. Para listas CORTAS (sí/no, local/foráneo, pocos valores) incluye las opciones en ask_user para elegir con un clic. Para un CAMPO o CATÁLOGO del ticket (empleado IDS, estado, categoría, etc.), NO copies sus valores: pon el encabezado exacto del campo en "field" y deja "options" vacío; el sistema mostrará un buscador con autocompletado. La pregunta va SOLO dentro de ask_user, no la repitas como texto.';
        $lines[] = '3. Pregunta solo lo esencial para armar los tickets; no pidas campos opcionales salvo que el usuario los mencione. Agrupa cuando sea natural y sé eficiente para no gastar tokens de más.';
        $lines[] = '4. Cuando ya tengas lo necesario, llama a propose_tickets con la lista completa. No describas las filas en texto: pásalas por la herramienta. El operador las revisará y editará en una tabla antes de crearlas.';
        $lines[] = '';
        $lines[] = 'REGLAS ESTRICTAS:';
        $lines[] = '- Mantente SIEMPRE en tu propósito: preparar tickets. Si el mensaje no trata de crear tickets, declina en una frase y pide los datos de los tickets. No respondas temas ajenos ni uses emojis o encabezados Markdown.';
        $lines[] = "- Nunca inventes valores de catálogo. Para los campos de lista usa EXACTAMENTE uno de los valores permitidos que se listan abajo.";
        $lines[] = '- Fechas en formato YYYY-MM-DD HH:MM (por ejemplo 2026-03-15 09:30).';
        $lines[] = "- El ESTATUS es SIEMPRE «" . self::FORCED_STATUS . "». No preguntes por el estatus ni uses otro valor; ya está definido por regla.";
        $lines[] = "- Propón como máximo {$max} tickets por solicitud.";
        $lines[] = '- Respeta los campos requeridos; si no tienes un dato requerido, pregúntalo antes de proponer.';
        $lines[] = '';
        $lines[] = 'CAMPOS DISPONIBLES (encabezado · tipo · requerido · valores):';

        foreach ($columns as $c) {
            if (! empty($c['hidden'])) {
                continue;
            }
            $req  = $c['required'] ? 'requerido' : 'opcional';
            $line = "- {$c['header']} · {$c['type']} · {$req}";
            if (! empty($c['options'])) {
                $opts  = $c['options'];
                $count = count($opts);
                if ($count > self::PROMPT_OPTION_LIMIT) {
                    $shown = implode(' | ', array_slice($opts, 0, self::PROMPT_OPTION_LIMIT));
                    $line .= " · valores permitidos (usa uno EXACTO; hay {$count} en total, aquí los primeros " . self::PROMPT_OPTION_LIMIT . "): {$shown}";
                } else {
                    $line .= ' · valores permitidos: ' . implode(' | ', $opts);
                }
            }
            $lines[] = $line;
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

    /**
     * The ask_user tool: lets the assistant ask ONE question at a time and offer
     * clickable options, instead of dumping a long list of questions.
     */
    private function askTool(): array
    {
        return [
            'name'        => 'ask_user',
            'description' => 'Haz UNA sola pregunta al operador para reunir lo que falta. Usa esta herramienta en lugar de escribir listas largas de preguntas. Para elegir de un conjunto: si es una lista CORTA (sí/no, local/foráneo, pocos valores) pásalos en "options" para elegir con un clic. Si la respuesta corresponde a un CAMPO/CATÁLOGO del ticket (por ejemplo el empleado IDS, el estado, la categoría), NO copies el catálogo: pon en "field" el encabezado EXACTO de ese campo y deja "options" vacío; el sistema mostrará el buscador con los valores reales.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'question'        => ['type' => 'string', 'description' => 'La pregunta, breve y clara. Solo una pregunta.'],
                    'field'           => ['type' => 'string', 'description' => 'Encabezado EXACTO de la columna/campo cuya respuesta pides (de la lista CAMPOS DISPONIBLES). Úsalo para catálogos grandes en vez de copiar sus valores en options.'],
                    'options'         => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Opciones para elegir con un clic, SOLO para listas cortas. Déjalo vacío si usas "field" o si la respuesta es texto libre.'],
                    'allow_free_text' => ['type' => 'boolean', 'description' => 'true si, además de las opciones, el usuario también puede escribir su propia respuesta.'],
                ],
                'required'   => ['question'],
            ],
        ];
    }

    /**
     * Turns a raw ask_user tool input into the question payload the frontend
     * renders. Key optimization: when the model names a `field` (a column
     * header), the real catalog is resolved from the plan here instead of the
     * model re-emitting it — that keeps large lists out of the conversation
     * history (smaller session, fewer tokens) and lets the UI autocomplete
     * against the real values. Large lists become a search box, not chips.
     *
     * @return array{text:string,options:string[],field:?string,autocomplete:bool,allowFreeText:bool}
     */
    private function buildQuestion(array $askInput, array $columns): array
    {
        $modelOptions = array_values(array_filter(array_map(
            static fn($o) => trim((string) $o),
            is_array($askInput['options'] ?? null) ? $askInput['options'] : [],
        ), static fn($o) => $o !== ''));

        // Resolve the named field to a real column (case-insensitive header match).
        $field        = null;
        $fieldOptions = [];
        $rawField     = trim((string) ($askInput['field'] ?? ''));
        if ($rawField !== '') {
            $needle = mb_strtoupper($rawField);
            foreach ($columns as $c) {
                if (mb_strtoupper($c['header']) === $needle) {
                    $field        = $c['header'];
                    $fieldOptions = array_values($c['options'] ?? []);
                    break;
                }
            }
        }

        // The frontend reads a resolved field's options from `columns` (already
        // sent), so we don't duplicate a large list in the question payload.
        $effectiveCount = $field !== null ? count($fieldOptions) : count($modelOptions);

        return [
            'text'          => trim((string) ($askInput['question'] ?? '')),
            'options'       => $field !== null ? [] : $modelOptions,
            'field'         => $field,
            'autocomplete'  => $effectiveCount > self::AUTOCOMPLETE_THRESHOLD,
            'allowFreeText' => (bool) ($askInput['allow_free_text'] ?? ($effectiveCount === 0)),
        ];
    }

    /**
     * The propose_tickets tool. Its item schema mirrors the grid columns; small
     * catalog lists become enums (hard grounding); large ones are validated
     * server-side instead of bloating the schema.
     */
    private function proposeTool(array $columns): array
    {
        $props    = [];
        $required = [];
        foreach ($columns as $c) {
            if (! empty($c['hidden'])) {
                continue;
            }
            $prop = ['type' => 'string'];
            $desc = $c['required'] ? 'Requerido. ' : 'Opcional. ';
            $desc .= match ($c['type']) {
                'date'     => 'Fecha en formato YYYY-MM-DD HH:MM.',
                'number'   => 'Valor numérico.',
                'textarea' => 'Texto (puede ser largo).',
                default    => 'Texto.',
            };

            if (! empty($c['options'])) {
                if (count($c['options']) <= self::ENUM_LIMIT) {
                    $prop['enum'] = array_values($c['options']);
                } else {
                    $desc .= ' Debe ser exactamente uno de los valores del catálogo (ver instrucciones).';
                }
            }
            $prop['description'] = $desc;
            $props[$c['header']] = $prop;
            if ($c['required']) {
                $required[] = $c['header'];
            }
        }

        return [
            'name'        => 'propose_tickets',
            'description' => 'Registra la lista de tickets propuestos para que el usuario los revise y edite antes de crearlos. Úsala solo cuando tengas datos suficientes para al menos un ticket.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'tickets' => [
                        'type'        => 'array',
                        'description' => 'Lista de tickets a crear. Cada elemento es un ticket con sus campos.',
                        'items'       => [
                            'type'       => 'object',
                            'properties' => $props,
                            'required'   => $required,
                        ],
                    ],
                ],
                'required'   => ['tickets'],
            ],
        ];
    }

    /**
     * Maps the model's proposed tickets to clean rows keyed by the exact grid
     * headers (matching case-insensitively, dropping unknown keys).
     *
     * @param array<int,mixed> $tickets
     * @return array<int,array<string,string>>
     */
    private function normalizeProposal(array $tickets, array $columns): array
    {
        $headerByUpper = [];
        foreach ($columns as $c) {
            $headerByUpper[mb_strtoupper($c['header'])] = $c['header'];
        }

        $max          = $this->settings->aiMaxTicketsPerRequest();
        $statusHeader = $this->statusHeader($columns);
        $rows         = [];
        foreach ($tickets as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }
            $row = [];
            foreach ($columns as $c) {
                $row[$c['header']] = '';
            }
            foreach ($ticket as $key => $value) {
                $header = $headerByUpper[mb_strtoupper((string) $key)] ?? null;
                if ($header !== null) {
                    $row[$header] = is_scalar($value) ? trim((string) $value) : '';
                }
            }
            // Enforce the business rule regardless of what the model proposed.
            if ($statusHeader !== null) {
                $row[$statusHeader] = self::FORCED_STATUS;
            }
            $rows[] = $row;
            if (count($rows) >= $max) {
                break;
            }
        }
        return $rows;
    }

    // ------------------------------------------------------------------
    // Workbook writing (source for the import pipeline)
    // ------------------------------------------------------------------

    /**
     * Writes a minimal import workbook the validator/importer understand: a
     * DATOS sheet (headers + rows) plus the very-hidden _META sheet recording
     * the target containers. No _LISTAS is needed (validation re-derives options).
     *
     * @param string[]                       $headers
     * @param array<int,array<string,mixed>> $rows
     * @param int[]                          $containerIds
     */
    private function writeWorkbook(string $path, array $containerIds, array $headers, array $rows): void
    {
        $spreadsheet = new Spreadsheet();

        $datos = $spreadsheet->getActiveSheet();
        $datos->setTitle('DATOS');
        foreach ($headers as $i => $header) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $datos->getCell($letter . '1')->setValue($header);
        }
        $r = 2;
        foreach ($rows as $row) {
            foreach ($headers as $i => $header) {
                $value = trim((string) ($row[$header] ?? ''));
                if ($value === '') {
                    continue;
                }
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $datos->getCell($letter . $r)->setValueExplicit(
                    $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
            $r++;
        }

        $meta = $spreadsheet->createSheet();
        $meta->setTitle('_META');
        $meta->getCell('A1')->setValue('containers');
        $meta->getCell('B1')->setValue(implode(',', $containerIds));
        $meta->getCell('A2')->setValue('generated_at');
        $meta->getCell('B2')->setValue(date('Y-m-d H:i:s'));
        $meta->getCell('A3')->setValue('source');
        $meta->getCell('B3')->setValue('ai_creator');
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        $spreadsheet->setActiveSheetIndex(0);
        (new XlsxWriter($spreadsheet))->save($path);
    }

    // ------------------------------------------------------------------
    // Usage logging
    // ------------------------------------------------------------------

    private function logUsage(?int $userId, string $kind, string $model, $usage, int $proposed, int $created): void
    {
        $in  = $usage->inputTokens ?? 0;
        $out = $usage->outputTokens ?? 0;
        $cr  = $usage->cacheReadInputTokens ?? 0;
        $cw  = $usage->cacheCreationInputTokens ?? 0;

        try {
            $this->usage->insert([
                'user_id'            => $userId,
                'kind'               => $kind,
                'model'              => $model,
                'input_tokens'       => (int) $in,
                'output_tokens'      => (int) $out,
                'cache_read_tokens'  => (int) $cr,
                'cache_write_tokens' => (int) $cw,
                'tickets_proposed'   => $proposed,
                'tickets_created'    => $created,
                'estimated_cost'     => $this->estimateCost($model, (int) $in, (int) $out, (int) $cr, (int) $cw),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[ServiceDesk][AI] usage log failed: ' . $e->getMessage());
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

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
