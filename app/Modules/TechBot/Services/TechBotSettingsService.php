<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Services\CredentialCipher;
use App\Modules\TechBot\Models\TechBotSettingsModel;

/**
 * Typed accessor over techbot_settings. The SuperAdmin edits these from the
 * panel; the webhook/conversation services consume them at runtime.
 *
 * Secrets (Telegram bot token and webhook secret) are stored encrypted with the
 * app's encryption.key, exactly like Provisioning/ServiceDesk credentials, and
 * are only overwritten when a new value is submitted (blank = keep current).
 */
class TechBotSettingsService
{
    /** Default persona/scope block for the AI formatter (spec §9.2). */
    public const AI_DEFAULT_SYSTEM_PROMPT = <<<'TXT'
Eres un asistente que estructura notas tecnicas de soporte en campo.
Recibes texto libre de un tecnico de campo y lo organizas en un formato limpio y profesional.

Reglas:
- Mantener TODA la informacion que el tecnico proporciono, no omitir nada.
- No inventar informacion que el tecnico no menciono.
- Organizar en secciones claras: problema encontrado, accion realizada, resultado.
- Si el tecnico menciona refacciones, listarlas por separado.
- Si el tecnico menciona equipos (marca, modelo, serie), destacarlos.
- Usar lenguaje profesional pero sin alterar los hechos.
- Responder SOLO con el texto formateado, sin explicaciones adicionales.
- Maximo 500 palabras.
TXT;

    private ?CredentialCipher $cipher = null;

    public function __construct(
        private TechBotSettingsModel $model,
    ) {}

    public function all(): array
    {
        return $this->model->getAll();
    }

    // ------------------------------------------------------------------
    // Telegram bot
    // ------------------------------------------------------------------

    public function botEnabled(): bool
    {
        return $this->model->get('bot_enabled', '0') === '1';
    }

    /** Decrypted bot token ('' when unset or the cipher is unavailable). */
    public function botToken(): string
    {
        return $this->cipher()->decrypt($this->model->get('telegram_bot_token', ''));
    }

    public function hasBotToken(): bool
    {
        return trim($this->model->get('telegram_bot_token', '')) !== '';
    }

    public function botUsername(): string
    {
        return trim($this->model->get('telegram_bot_username', ''));
    }

    /** Decrypted webhook secret ('' when unset). */
    public function webhookSecret(): string
    {
        return $this->cipher()->decrypt($this->model->get('telegram_webhook_secret', ''));
    }

    public function hasWebhookSecret(): bool
    {
        return trim($this->model->get('telegram_webhook_secret', '')) !== '';
    }

    /**
     * Returns the webhook secret, generating and persisting one (encrypted) the
     * first time it is needed so the admin never has to invent it. Returns '' if
     * the cipher is unavailable (cannot store it safely).
     */
    public function ensureWebhookSecret(): string
    {
        $current = $this->webhookSecret();
        if ($current !== '') {
            return $current;
        }
        if (! $this->cipher()->isAvailable()) {
            return '';
        }
        $secret = bin2hex(random_bytes(24));
        $this->model->set('telegram_webhook_secret', $this->cipher()->encrypt($secret));
        return $secret;
    }

    public function welcomeMessage(): string
    {
        $v = trim($this->model->get('welcome_message', ''));
        return $v !== '' ? $v : 'Tu cuenta ha sido vinculada exitosamente. Ya puedes consultar y documentar tus tickets desde aqui.';
    }

    public function requirePhotoOnResolution(): bool
    {
        return $this->model->get('require_photo_on_resolution', '0') === '1';
    }

    public function requireVistoBuenoOnResolution(): bool
    {
        return $this->model->get('require_visto_bueno_on_resolution', '1') === '1';
    }

    public function allowResolucionArbitraria(): bool
    {
        return $this->model->get('allow_resolucion_arbitraria', '0') === '1';
    }

    /** Whether the bot is fully operable: enabled, has a token, cipher available. */
    public function botReady(): bool
    {
        return $this->botEnabled() && $this->hasBotToken() && $this->cipher()->isAvailable();
    }

    // ------------------------------------------------------------------
    // AI formatting (reuses ServiceDesk's Claude key/model; own toggle)
    // ------------------------------------------------------------------

    public function aiFormattingEnabled(): bool
    {
        return $this->model->get('ai_formatting_enabled', '0') === '1';
    }

    public function aiMaxTokens(): int
    {
        return max(256, (int) $this->model->get('ai_max_tokens', '1024'));
    }

    public function aiSystemPrompt(): string
    {
        $v = trim($this->model->get('ai_system_prompt', ''));
        return $v !== '' ? $v : self::AI_DEFAULT_SYSTEM_PROMPT;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    /**
     * Persists the settings form. Secrets are written encrypted and only when a
     * new (non-blank) value is submitted.
     */
    public function save(array $input): ServiceResult
    {
        $data = [
            'bot_enabled'                     => ! empty($input['bot_enabled']) ? '1' : '0',
            'telegram_bot_username'           => ltrim(trim((string) ($input['telegram_bot_username'] ?? '')), '@'),
            'welcome_message'                 => trim((string) ($input['welcome_message'] ?? '')),
            'require_photo_on_resolution'     => ! empty($input['require_photo_on_resolution']) ? '1' : '0',
            'require_visto_bueno_on_resolution' => ! empty($input['require_visto_bueno_on_resolution']) ? '1' : '0',
            'allow_resolucion_arbitraria'     => ! empty($input['allow_resolucion_arbitraria']) ? '1' : '0',
            'ai_formatting_enabled'           => ! empty($input['ai_formatting_enabled']) ? '1' : '0',
            'ai_max_tokens'                   => (string) max(256, (int) ($input['ai_max_tokens'] ?? 1024)),
            'ai_system_prompt'                => trim((string) ($input['ai_system_prompt'] ?? '')),
        ];

        $newToken  = trim((string) ($input['telegram_bot_token'] ?? ''));
        $newSecret = trim((string) ($input['telegram_webhook_secret'] ?? ''));

        if ($newToken !== '' || $newSecret !== '') {
            if (! $this->cipher()->isAvailable()) {
                return ServiceResult::fail('No se pueden cifrar los secretos: falta encryption.key en el entorno.');
            }
            if ($newToken !== '') {
                $data['telegram_bot_token'] = $this->cipher()->encrypt($newToken);
            }
            if ($newSecret !== '') {
                $data['telegram_webhook_secret'] = $this->cipher()->encrypt($newSecret);
            }
        }

        $this->model->setMany($data);

        return ServiceResult::ok(null, 'Configuración de TechBot guardada.');
    }

    private function cipher(): CredentialCipher
    {
        return $this->cipher ??= new CredentialCipher();
    }
}
