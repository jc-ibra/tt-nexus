<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Services\CredentialCipher;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Models\ServiceDeskSettingsModel;

/**
 * Typed accessor over servicedesk_settings. The SuperAdmin edits these; the
 * ServiceDesk operators consume them (import ceilings, GLPI targets, pacing).
 */
class ServiceDeskSettings
{
    /** Default Claude model for the AI creator. */
    public const AI_DEFAULT_MODEL = 'claude-haiku-4-5';

    /** Models the admin may pick for the AI creator (id => label). */
    public const AI_MODELS = [
        'claude-haiku-4-5' => 'Haiku 4.5 (rápido y económico)',
        'claude-sonnet-5'  => 'Sonnet 5 (mayor calidad)',
        'claude-opus-4-8'  => 'Opus 4.8 (máxima capacidad)',
    ];

    /**
     * Editable "persona / scope / style" block of the assistant's system prompt.
     * The service always appends the technical rules and the live catalog fields
     * after this, so the grounding cannot be broken by editing this text.
     */
    public const AI_DEFAULT_INSTRUCTIONS = <<<'TXT'
Eres un asistente especializado ÚNICAMENTE en preparar la creación de tickets en la mesa de servicio (GLPI). Tu único propósito es ayudar al operador a armar tickets casi idénticos (por ejemplo 10 a 15) para una misma actividad, donde solo cambian algunos datos.

ALCANCE (estricto):
- Solo atiendes temas relacionados con preparar tickets: qué tickets crear, cuántos, con qué datos, categorías, fechas y campos del contenedor.
- Si te preguntan cualquier otra cosa (conocimiento general, matemáticas, opiniones, programación, temas ajenos), NO respondas el tema. Declina en una frase y reencauza: aclara que solo puedes ayudar a preparar tickets y pregunta qué tickets necesita crear.
- No des explicaciones enciclopédicas, definiciones ni tutoriales de temas ajenos, aunque insistan.

ESTILO:
- Español, claro y breve. Sin emojis. Sin encabezados Markdown ni tablas decorativas; usa texto plano y, si acaso, listas simples.
- Haz preguntas concretas solo cuando falte información para armar los tickets.
TXT;

    /**
     * Persona/scope/style block of the SELF-SERVICE WIDGET assistant. The widget
     * faces end users (not operators), so the tone is warmer and the goal is a
     * single ticket. Technical rules and catalogs are appended by code.
     */
    public const WIDGET_DEFAULT_INSTRUCTIONS = <<<'TXT'
Eres el asistente de autoservicio de la mesa de ayuda. Ayudas a las personas de la organización a levantar UN ticket de soporte de forma rápida y amable.

ALCANCE (estricto):
- Solo ayudas a reportar una falla o a solicitar un requerimiento de TI y a levantar un único ticket.
- Si preguntan otra cosa (conocimiento general, temas ajenos), declina en una frase de forma amable y pide que describan su problema o su solicitud.

ESTILO:
- Español, cálido, claro y breve. Sin tecnicismos, sin emojis, sin encabezados Markdown.
- Haz una sola pregunta a la vez y solo cuando de verdad falte información.
TXT;

    private ?CredentialCipher $cipher = null;

    public function __construct(
        private ServiceDeskSettingsModel $model,
    ) {}

    public function all(): array
    {
        return $this->model->getAll();
    }

    public function importMaxRows(): int
    {
        return max(0, (int) $this->model->get('import_max_rows', '500'));
    }

    public function batchSize(): int
    {
        return max(1, (int) $this->model->get('import_batch_size', '30'));
    }

    public function batchPauseSeconds(): int
    {
        return max(0, (int) $this->model->get('import_batch_pause_sec', '2'));
    }

    public function entitiesId(): int
    {
        return max(0, (int) $this->model->get('glpi_entities_id', '0'));
    }

    public function requesterUserId(): int
    {
        return max(0, (int) $this->model->get('glpi_requester_user_id', '0'));
    }

    /**
     * @return int[] container ids the admin allows (empty = all active).
     */
    public function includedContainerIds(): array
    {
        $raw = trim($this->model->get('included_container_ids', ''));
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn($v) => (int) trim($v),
            explode(',', $raw),
        ), static fn($v) => $v > 0));
    }

    public function autocreateCatalogValues(): bool
    {
        return $this->model->get('autocreate_catalog_values', '0') === '1';
    }

    public function importEnabled(): bool
    {
        return $this->model->get('import_enabled', '1') === '1';
    }

    /**
     * Persists a settings form. Only known keys are written.
     */
    public function save(array $input): ServiceResult
    {
        $maxRows   = max(0, (int) ($input['import_max_rows'] ?? 500));
        $batch     = max(1, (int) ($input['import_batch_size'] ?? 30));
        $pause     = max(0, (int) ($input['import_batch_pause_sec'] ?? 2));
        $entities  = max(0, (int) ($input['glpi_entities_id'] ?? 0));
        $requester = max(0, (int) ($input['glpi_requester_user_id'] ?? 0));

        // Normalize the container id list to a clean CSV of positive ints.
        $containers = $input['included_container_ids'] ?? '';
        if (is_array($containers)) {
            $containers = implode(',', $containers);
        }
        $containerCsv = implode(',', array_values(array_filter(array_map(
            static fn($v) => (int) trim((string) $v),
            explode(',', (string) $containers),
        ), static fn($v) => $v > 0)));

        $this->model->setMany([
            'import_max_rows'          => (string) $maxRows,
            'import_batch_size'        => (string) $batch,
            'import_batch_pause_sec'   => (string) $pause,
            'glpi_entities_id'         => (string) $entities,
            'glpi_requester_user_id'   => (string) $requester,
            'included_container_ids'   => $containerCsv,
            'autocreate_catalog_values' => ! empty($input['autocreate_catalog_values']) ? '1' : '0',
            'import_enabled'           => ! empty($input['import_enabled']) ? '1' : '0',
        ]);

        return ServiceResult::ok(null, 'Configuración guardada.');
    }

    // ------------------------------------------------------------------
    // AI ticket creator (Claude API)
    // ------------------------------------------------------------------

    public function aiEnabled(): bool
    {
        return $this->model->get('ai_enabled', '0') === '1';
    }

    public function aiModel(): string
    {
        $model = trim($this->model->get('ai_model', self::AI_DEFAULT_MODEL));
        return isset(self::AI_MODELS[$model]) ? $model : self::AI_DEFAULT_MODEL;
    }

    /** Editable persona/scope/style instructions (falls back to the default). */
    public function aiSystemPrompt(): string
    {
        $v = trim($this->model->get('ai_system_prompt', ''));
        return $v !== '' ? $v : self::AI_DEFAULT_INSTRUCTIONS;
    }

    /** Decrypted Claude API key ('' when unset or the cipher is unavailable). */
    public function aiApiKey(): string
    {
        return $this->cipher()->decrypt($this->model->get('ai_api_key', ''));
    }

    public function aiHasApiKey(): bool
    {
        return trim($this->model->get('ai_api_key', '')) !== '';
    }

    public function aiMaxTicketsPerRequest(): int
    {
        return max(1, (int) $this->model->get('ai_max_tickets_per_request', '25'));
    }

    /** Daily token ceiling (input+output). 0 = unlimited. */
    public function aiDailyTokenBudget(): int
    {
        return max(0, (int) $this->model->get('ai_daily_token_budget', '0'));
    }

    /**
     * Whether the AI creator is fully usable: enabled, has a key, and the
     * cipher (encryption.key) is available to read it.
     */
    public function aiReady(): bool
    {
        return $this->aiEnabled() && $this->aiHasApiKey() && $this->cipher()->isAvailable();
    }

    /**
     * Persists the AI settings form. The API key is stored encrypted and only
     * overwritten when a new value is submitted (blank leaves the current key).
     */
    public function saveAi(array $input): ServiceResult
    {
        $model = trim((string) ($input['ai_model'] ?? self::AI_DEFAULT_MODEL));
        if (! isset(self::AI_MODELS[$model])) {
            $model = self::AI_DEFAULT_MODEL;
        }

        // Empty instructions -> store empty; aiSystemPrompt() falls back to the default.
        $instructions = trim((string) ($input['ai_system_prompt'] ?? ''));

        $data = [
            'ai_enabled'                 => ! empty($input['ai_enabled']) ? '1' : '0',
            'ai_model'                   => $model,
            'ai_max_tickets_per_request' => (string) max(1, (int) ($input['ai_max_tickets_per_request'] ?? 25)),
            'ai_daily_token_budget'      => (string) max(0, (int) ($input['ai_daily_token_budget'] ?? 0)),
            'ai_system_prompt'           => $instructions,
        ];

        $newKey = trim((string) ($input['ai_api_key'] ?? ''));
        if ($newKey !== '') {
            if (! $this->cipher()->isAvailable()) {
                return ServiceResult::fail('No se puede cifrar la API key: falta encryption.key en el entorno.');
            }
            $data['ai_api_key'] = $this->cipher()->encrypt($newKey);
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración de IA guardada.');
    }

    // ------------------------------------------------------------------
    // Self-service widget (embeddable chat -> single ticket)
    // ------------------------------------------------------------------

    public function widgetEnabled(): bool
    {
        return $this->model->get('widget_enabled', '0') === '1';
    }

    /**
     * Public site key that identifies this widget install. Generated (and
     * persisted) lazily the first time it is needed so the admin never sees an
     * empty value.
     */
    public function widgetSiteKey(): string
    {
        $key = trim($this->model->get('widget_site_key', ''));
        if ($key === '') {
            $key = 'wgt_' . bin2hex(random_bytes(16));
            $this->model->set('widget_site_key', $key);
        }
        return $key;
    }

    /**
     * Origins the widget may be embedded from, in origin form
     * (scheme://host[:port]). Empty means "nowhere but same-origin".
     *
     * @return string[]
     */
    public function widgetAllowedOrigins(): array
    {
        $raw   = (string) $this->model->get('widget_allowed_origins', '');
        $out   = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $part) {
            $part = rtrim(trim($part), '/');
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    /** Hardware plugin container id (0 = hardware capture disabled). */
    public function widgetContainerId(): int
    {
        return max(0, (int) $this->model->get('widget_container_id', '0'));
    }

    public function widgetFieldEquipo(): string
    {
        return trim($this->model->get('widget_field_equipo', ''));
    }

    public function widgetFieldModelo(): string
    {
        return trim($this->model->get('widget_field_modelo', ''));
    }

    public function widgetFieldSerie(): string
    {
        return trim($this->model->get('widget_field_serie', ''));
    }

    public function widgetFieldCategoria(): string
    {
        return trim($this->model->get('widget_field_categoria', ''));
    }

    /** Fixed ITIL category id every widget ticket is filed under (0 = none). */
    public function widgetItilCategoryId(): int
    {
        return max(0, (int) $this->model->get('widget_itil_category_id', '0'));
    }

    /** Generic GLPI requester for widget tickets (falls back to the import default). */
    public function widgetRequesterUserId(): int
    {
        $v = (int) $this->model->get('widget_requester_user_id', '0');
        return $v > 0 ? $v : $this->requesterUserId();
    }

    /** Destination entity for widget tickets (falls back to the import default). */
    public function widgetEntitiesId(): int
    {
        $raw = trim((string) $this->model->get('widget_entities_id', ''));
        return $raw === '' ? $this->entitiesId() : max(0, (int) $raw);
    }

    /**
     * GLPI request source (requesttypes_id) stamped on every widget ticket, so
     * they are traceable to the widget in GLPI. 0 = leave it to GLPI's default.
     */
    public function widgetRequestSourceId(): int
    {
        return max(0, (int) $this->model->get('widget_request_source_id', '0'));
    }

    public function widgetSystemPrompt(): string
    {
        $v = trim($this->model->get('widget_system_prompt', ''));
        return $v !== '' ? $v : self::WIDGET_DEFAULT_INSTRUCTIONS;
    }

    /** Max widget requests per IP per hour (0 = unlimited). */
    public function widgetRateLimitPerHour(): int
    {
        return max(0, (int) $this->model->get('widget_rate_limit_per_hour', '20'));
    }

    public function widgetTitle(): string
    {
        $v = trim($this->model->get('widget_title', ''));
        return $v !== '' ? $v : 'Soporte';
    }

    public function widgetWelcome(): string
    {
        $v = trim($this->model->get('widget_welcome', ''));
        return $v !== '' ? $v : 'Hola, cuéntame qué problema tienes y te ayudo a levantar un ticket de soporte.';
    }

    /** Whether hardware capture is fully configured (container + at least the equipo field). */
    public function widgetHardwareConfigured(): bool
    {
        return $this->widgetContainerId() > 0 && $this->widgetFieldEquipo() !== '';
    }

    /**
     * Whether the widget can actually create tickets: enabled, the AI is ready,
     * and a fixed ITIL category has been chosen.
     */
    public function widgetReady(): bool
    {
        return $this->widgetEnabled() && $this->aiReady() && $this->widgetItilCategoryId() > 0;
    }

    /** Sets the fixed widget ITIL category (called from the categories screen). */
    public function setWidgetCategory(int $categoryId): void
    {
        $this->model->set('widget_itil_category_id', (string) max(0, $categoryId));
    }

    /**
     * Persists the widget configuration form. The API key/model are shared with
     * the AI creator (saveAi), so they are not touched here.
     */
    public function saveWidget(array $input): ServiceResult
    {
        $origins = [];
        foreach (preg_split('/[\r\n,]+/', (string) ($input['widget_allowed_origins'] ?? '')) ?: [] as $o) {
            $o = rtrim(trim($o), '/');
            if ($o !== '') {
                $origins[] = $o;
            }
        }

        $entitiesRaw = trim((string) ($input['widget_entities_id'] ?? ''));

        $data = [
            'widget_enabled'             => ! empty($input['widget_enabled']) ? '1' : '0',
            'widget_title'               => trim((string) ($input['widget_title'] ?? '')),
            'widget_welcome'             => trim((string) ($input['widget_welcome'] ?? '')),
            'widget_system_prompt'       => trim((string) ($input['widget_system_prompt'] ?? '')),
            'widget_allowed_origins'     => implode("\n", array_values(array_unique($origins))),
            'widget_container_id'        => (string) max(0, (int) ($input['widget_container_id'] ?? 0)),
            'widget_field_equipo'        => trim((string) ($input['widget_field_equipo'] ?? '')),
            'widget_field_modelo'        => trim((string) ($input['widget_field_modelo'] ?? '')),
            'widget_field_serie'         => trim((string) ($input['widget_field_serie'] ?? '')),
            'widget_field_categoria'     => trim((string) ($input['widget_field_categoria'] ?? '')),
            'widget_requester_user_id'   => (string) max(0, (int) ($input['widget_requester_user_id'] ?? 0)),
            'widget_entities_id'         => $entitiesRaw === '' ? '' : (string) max(0, (int) $entitiesRaw),
            'widget_request_source_id'   => (string) max(0, (int) ($input['widget_request_source_id'] ?? 0)),
            'widget_rate_limit_per_hour' => (string) max(0, (int) ($input['widget_rate_limit_per_hour'] ?? 20)),
        ];

        // Regenerate the site key on request (invalidates every existing embed).
        if (! empty($input['regenerate_site_key'])) {
            $data['widget_site_key'] = 'wgt_' . bin2hex(random_bytes(16));
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración del widget guardada.');
    }

    // ------------------------------------------------------------------
    // Public self-service landing (standalone page -> single ticket)
    //
    // A public page at /soporte, independent from the embeddable widget: it has
    // its own enable toggle and site key, collects the requester identity in a
    // form (there is no intranet session), and lets the user PICK the ITIL
    // category from the supported list (unlike the widget's fixed category). It
    // shares the AI brain and the synchronous create pipeline.
    // ------------------------------------------------------------------

    public function landingEnabled(): bool
    {
        return $this->model->get('landing_enabled', '0') === '1';
    }

    /**
     * Public site key for the landing, independent from the widget's. Generated
     * (and persisted) lazily the first time it is needed. It is rendered into
     * the public page and required on the chat/ticket POSTs (kill-switch + a
     * light guard against off-page callers, alongside the same-origin check).
     */
    public function landingSiteKey(): string
    {
        $key = trim($this->model->get('landing_site_key', ''));
        if ($key === '') {
            $key = 'lnd_' . bin2hex(random_bytes(16));
            $this->model->set('landing_site_key', $key);
        }
        return $key;
    }

    public function landingTitle(): string
    {
        $v = trim($this->model->get('landing_title', ''));
        return $v !== '' ? $v : 'Mesa de Ayuda';
    }

    public function landingIntro(): string
    {
        $v = trim($this->model->get('landing_intro', ''));
        return $v !== '' ? $v : 'Completa tus datos, elige la categoría y cuéntame qué necesitas; te ayudo a levantar tu ticket de soporte.';
    }

    /** Max landing requests per IP per hour (0 = unlimited). Applies to POSTs. */
    public function landingRateLimitPerHour(): int
    {
        return max(0, (int) $this->model->get('landing_rate_limit_per_hour', '10'));
    }

    /**
     * Plugin container ids whose additional fields the public landing form
     * requests (empty = no extra fields, only the base ticket fields). The
     * SuperAdmin picks these, mirroring the /servicedesk/creator container list.
     *
     * @return int[]
     */
    public function landingContainerIds(): array
    {
        $raw = trim($this->model->get('landing_container_ids', ''));
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn($v) => (int) trim($v),
            explode(',', $raw),
        ), static fn($v) => $v > 0));
    }

    /**
     * Whether the public landing's COMPLETE FORM can create tickets: enabled and
     * at least one ITIL category is marked as supported (the user picks one).
     * The manual form does not need the AI.
     */
    public function landingFormReady(): bool
    {
        return $this->landingEnabled()
            && (new ServiceDeskCategoryMapModel())->hasSupported();
    }

    /**
     * Whether the landing's FLOATING CHAT can create tickets: the form is ready
     * AND the AI is configured. The chat is the optional assistant on top of the
     * form.
     */
    public function landingChatReady(): bool
    {
        return $this->landingFormReady() && $this->aiReady();
    }

    /** Back-compat alias: the chat path (chat()/createTicket) gates on chat readiness. */
    public function landingReady(): bool
    {
        return $this->landingChatReady();
    }

    /**
     * Persists the landing configuration form. The API key/model are shared with
     * the AI creator (saveAi) and the assistant prompt with the widget, so they
     * are not touched here.
     */
    public function saveLanding(array $input): ServiceResult
    {
        // Normalize the container id list to a clean CSV of positive ints.
        $containers = $input['landing_container_ids'] ?? '';
        if (is_array($containers)) {
            $containers = implode(',', $containers);
        }
        $containerCsv = implode(',', array_values(array_filter(array_map(
            static fn($v) => (int) trim((string) $v),
            explode(',', (string) $containers),
        ), static fn($v) => $v > 0)));

        $data = [
            'landing_enabled'             => ! empty($input['landing_enabled']) ? '1' : '0',
            'landing_title'               => trim((string) ($input['landing_title'] ?? '')),
            'landing_intro'               => trim((string) ($input['landing_intro'] ?? '')),
            'landing_rate_limit_per_hour' => (string) max(0, (int) ($input['landing_rate_limit_per_hour'] ?? 10)),
            'landing_container_ids'       => $containerCsv,
        ];

        // Regenerate the site key on request (invalidates the current URL's key).
        if (! empty($input['regenerate_landing_key'])) {
            $data['landing_site_key'] = 'lnd_' . bin2hex(random_bytes(16));
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración de la landing guardada.');
    }

    // ------------------------------------------------------------------
    // Backlog report (daily GLPI open-ticket digest by email)
    // ------------------------------------------------------------------

    public function backlogEnabled(): bool
    {
        return $this->model->get('backlog_enabled', '0') === '1';
    }

    /** Cut-off hour in the app timezone, normalized to HH:MM. */
    public function backlogSendHour(): string
    {
        $raw = trim($this->model->get('backlog_send_hour', '08:00'));
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $m)) {
            return '08:00';
        }
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /** Whether the report is also sent on Saturdays and Sundays. */
    public function backlogWeekends(): bool
    {
        return $this->model->get('backlog_weekends', '1') === '1';
    }

    /** Last business date a scheduled/manual report was sent (YYYY-MM-DD or ''). */
    public function backlogLastSentDate(): string
    {
        return trim($this->model->get('backlog_last_sent_date', ''));
    }

    public function setBacklogLastSentDate(string $date): void
    {
        $this->model->set('backlog_last_sent_date', $date);
    }

    public function backlogFromName(): string
    {
        $v = trim($this->model->get('backlog_from_name', ''));
        return $v !== '' ? $v : 'Mesa de Ayuda';
    }

    /** Report sender address; falls back to the SMTP From address when unset. */
    public function backlogFromEmail(): string
    {
        $v = trim($this->model->get('backlog_from_email', ''));
        if ($v !== '') {
            return $v;
        }
        try {
            return (string) service('appSettings')->getSmtp()['smtp_from_email'] ?: (string) config('Email')->fromEmail;
        } catch (\Throwable) {
            return '';
        }
    }

    public function backlogSubjectPrefix(): string
    {
        $v = trim($this->model->get('backlog_subject_prefix', ''));
        return $v !== '' ? $v : 'Reporte Diario de Backlog';
    }

    public function backlogOrgLabel(): string
    {
        $v = trim($this->model->get('backlog_org_label', ''));
        return $v !== '' ? $v : 'Mesa de Ayuda';
    }

    /** Age (days) at/over which an open ticket counts as "crítico". */
    public function backlogCriticalDays(): int
    {
        return max(1, (int) $this->model->get('backlog_critical_days', '30'));
    }

    /** Direct recipients (To). @return string[] */
    public function backlogTo(): array
    {
        return $this->parseEmails($this->model->get('backlog_to', ''));
    }

    /** Copied recipients (CC). @return string[] */
    public function backlogCc(): array
    {
        return $this->parseEmails($this->model->get('backlog_cc', ''));
    }

    /** Plugin container id whose field marks IDC (0 = IDC KPI disabled). */
    public function backlogIdcContainerId(): int
    {
        return max(0, (int) $this->model->get('backlog_idc_container_id', '0'));
    }

    /** Plugin field name holding IDC; empty value on a ticket => "sin IDC". */
    public function backlogIdcField(): string
    {
        return trim($this->model->get('backlog_idc_field', ''));
    }

    /** Whether the "Sin IDC" KPI can be computed (container + field chosen). */
    public function backlogIdcConfigured(): bool
    {
        return $this->backlogIdcContainerId() > 0 && $this->backlogIdcField() !== '';
    }

    /** Plugin container id whose field marks the Regional (0 = section disabled). */
    public function backlogRegionalContainerId(): int
    {
        return max(0, (int) $this->model->get('backlog_regional_container_id', '0'));
    }

    /** Plugin field name holding the Regional; its value groups the "POR REGIONAL" table. */
    public function backlogRegionalField(): string
    {
        return trim($this->model->get('backlog_regional_field', ''));
    }

    /** Whether the "POR REGIONAL" section can be built (container + field chosen). */
    public function backlogRegionalConfigured(): bool
    {
        return $this->backlogRegionalContainerId() > 0 && $this->backlogRegionalField() !== '';
    }

    /**
     * Whether the report can actually be sent: enabled, has an audience, and a
     * sender address is resolvable.
     */
    public function backlogReady(): bool
    {
        return $this->backlogEnabled()
            && $this->backlogTo() !== []
            && $this->backlogFromEmail() !== '';
    }

    /**
     * Persists the backlog report configuration form.
     */
    public function saveBacklog(array $input): ServiceResult
    {
        $fromEmail = trim((string) ($input['backlog_from_email'] ?? ''));
        if ($fromEmail !== '' && ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::fail('El correo del remitente no es válido.');
        }

        $data = [
            'backlog_enabled'          => ! empty($input['backlog_enabled']) ? '1' : '0',
            'backlog_send_hour'        => $this->normalizeHour((string) ($input['backlog_send_hour'] ?? '08:00')),
            'backlog_weekends'         => ! empty($input['backlog_weekends']) ? '1' : '0',
            'backlog_from_name'        => trim((string) ($input['backlog_from_name'] ?? '')),
            'backlog_from_email'       => $fromEmail,
            'backlog_to'               => implode(', ', $this->parseEmails((string) ($input['backlog_to'] ?? ''))),
            'backlog_cc'               => implode(', ', $this->parseEmails((string) ($input['backlog_cc'] ?? ''))),
            'backlog_subject_prefix'   => trim((string) ($input['backlog_subject_prefix'] ?? '')),
            'backlog_org_label'        => trim((string) ($input['backlog_org_label'] ?? '')),
            'backlog_critical_days'    => (string) max(1, (int) ($input['backlog_critical_days'] ?? 30)),
            'backlog_idc_container_id' => (string) max(0, (int) ($input['backlog_idc_container_id'] ?? 0)),
            'backlog_idc_field'        => trim((string) ($input['backlog_idc_field'] ?? '')),
            'backlog_regional_container_id' => (string) max(0, (int) ($input['backlog_regional_container_id'] ?? 0)),
            'backlog_regional_field'        => trim((string) ($input['backlog_regional_field'] ?? '')),
        ];

        // Clearing a container also clears its field (keeps each pair coherent).
        if ((int) $data['backlog_idc_container_id'] === 0) {
            $data['backlog_idc_field'] = '';
        }
        if ((int) $data['backlog_regional_container_id'] === 0) {
            $data['backlog_regional_field'] = '';
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración del reporte de backlog guardada.');
    }

    /** Splits a free-text address list (commas/newlines/semicolons) into unique valid emails. */
    private function parseEmails(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($part)] = $part;
            }
        }
        return array_values($out);
    }

    private function normalizeHour(string $raw): string
    {
        $raw = trim($raw);
        return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $m)
            ? sprintf('%02d:%02d', (int) $m[1], (int) $m[2])
            : '08:00';
    }

    private function cipher(): CredentialCipher
    {
        return $this->cipher ??= new CredentialCipher();
    }
}
