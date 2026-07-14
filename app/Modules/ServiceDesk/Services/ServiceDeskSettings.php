<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Services\CredentialCipher;
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

    private function cipher(): CredentialCipher
    {
        return $this->cipher ??= new CredentialCipher();
    }
}
