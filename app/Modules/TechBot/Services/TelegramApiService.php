<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

/**
 * Thin wrapper over the Telegram Bot API (https://api.telegram.org/bot<token>/…).
 *
 * Sends messages/photos, answers callback queries, and performs the one-off
 * setup calls (getMe, setWebhook). Also downloads user-sent photos via
 * getFile + the file download URL so they can be forwarded to GLPI.
 *
 * The bot token comes from TechBotSettingsService (decrypted); it is never
 * logged. Every call returns ['ok'=>bool, 'result'=>mixed, 'error'=>?string].
 */
class TelegramApiService
{
    private const API_BASE  = 'https://api.telegram.org';
    private const TIMEOUT   = 20;

    public function __construct(
        private TechBotSettingsService $settings,
    ) {}

    // ------------------------------------------------------------------
    // Outgoing messages
    // ------------------------------------------------------------------

    /**
     * Sends a text message. Pass $inlineKeyboard as an array of button rows,
     * each button ['text'=>..., 'callback_data'=>...] or ['text'=>..., 'url'=>...].
     *
     * @param array<int,array<int,array<string,string>>>|null $inlineKeyboard
     */
    public function sendMessage(int $chatId, string $text, ?array $inlineKeyboard = null, ?string $parseMode = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text'    => $this->truncate($text, 4096),
        ];
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }
        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        }
        return $this->call('sendMessage', $payload);
    }

    /**
     * Sends a photo by URL or file_id, with an optional caption.
     */
    public function sendPhoto(int $chatId, string $photo, string $caption = ''): array
    {
        $payload = ['chat_id' => $chatId, 'photo' => $photo];
        if ($caption !== '') {
            $payload['caption'] = $this->truncate($caption, 1024);
        }
        return $this->call('sendPhoto', $payload);
    }

    /**
     * Acknowledges a callback query so Telegram stops the loading spinner on the
     * tapped inline button.
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== '') {
            $payload['text'] = $this->truncate($text, 200);
        }
        return $this->call('answerCallbackQuery', $payload);
    }

    /**
     * Removes the inline keyboard from a previously sent message (e.g. after the
     * technician taps an option) to prevent double taps.
     */
    public function editMessageReplyMarkup(int $chatId, int $messageId): array
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'reply_markup' => ['inline_keyboard' => []],
        ]);
    }

    // ------------------------------------------------------------------
    // Setup / diagnostics
    // ------------------------------------------------------------------

    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    /**
     * Registers the webhook URL with Telegram. The secret_token is echoed back
     * by Telegram in the X-Telegram-Bot-Api-Secret-Token header on every update,
     * which the webhook filter validates.
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->call('setWebhook', [
            'url'             => $url,
            'secret_token'    => $secretToken,
            'allowed_updates' => ['message', 'callback_query', 'edited_message'],
            'max_connections' => 40,
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => false]);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', []);
    }

    // ------------------------------------------------------------------
    // Incoming files (photos)
    // ------------------------------------------------------------------

    /**
     * Resolves a Telegram file_id to its temporary file_path via getFile.
     * Returns ['ok'=>bool,'file_path'=>?string,'error'=>?string].
     */
    public function getFile(string $fileId): array
    {
        $resp = $this->call('getFile', ['file_id' => $fileId]);
        if (! $resp['ok']) {
            return ['ok' => false, 'file_path' => null, 'error' => $resp['error']];
        }
        $path = $resp['result']['file_path'] ?? null;
        if (! $path) {
            return ['ok' => false, 'file_path' => null, 'error' => 'Telegram no devolvió file_path.'];
        }
        return ['ok' => true, 'file_path' => (string) $path, 'error' => null];
    }

    /**
     * Downloads a file's raw bytes given the file_path from getFile().
     * Returns ['ok'=>bool,'contents'=>?string,'error'=>?string].
     */
    public function downloadFile(string $filePath): array
    {
        $token = $this->settings->botToken();
        if ($token === '') {
            return ['ok' => false, 'contents' => null, 'error' => 'El bot no tiene token configurado.'];
        }

        $url  = self::API_BASE . '/file/bot' . $token . '/' . ltrim($filePath, '/');
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err  = curl_error($curl);
        curl_close($curl);

        if ($err || $code >= 400 || $body === false) {
            return ['ok' => false, 'contents' => null, 'error' => $err ?: "HTTP {$code} al descargar el archivo."];
        }
        return ['ok' => true, 'contents' => (string) $body, 'error' => null];
    }

    // ------------------------------------------------------------------
    // HTTP primitive
    // ------------------------------------------------------------------

    /**
     * Performs a JSON POST to a Bot API method and normalizes the envelope.
     */
    private function call(string $method, array $payload): array
    {
        $token = $this->settings->botToken();
        if ($token === '') {
            return ['ok' => false, 'result' => null, 'error' => 'El bot no tiene token configurado.'];
        }

        $url  = self::API_BASE . '/bot' . $token . '/' . $method;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            log_message('error', "[TelegramApi] cURL error on {$method}: {$err}");
            return ['ok' => false, 'result' => null, 'error' => 'Error de conexión con Telegram: ' . $err];
        }

        $data = json_decode((string) $response, true);
        if (! is_array($data)) {
            return ['ok' => false, 'result' => null, 'error' => "Respuesta inválida de Telegram (HTTP {$httpCode})."];
        }

        if (empty($data['ok'])) {
            $desc = (string) ($data['description'] ?? "HTTP {$httpCode}");
            log_message('error', "[TelegramApi] {$method} failed: {$desc}");
            return ['ok' => false, 'result' => null, 'error' => $desc];
        }

        return ['ok' => true, 'result' => $data['result'] ?? null, 'error' => null];
    }

    private function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }
}
