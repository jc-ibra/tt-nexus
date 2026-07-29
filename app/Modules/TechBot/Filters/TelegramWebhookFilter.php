<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Authenticates the public Telegram webhook. It is NOT behind auth/module access
 * (Telegram cannot log in); instead it is guarded by the shared secret that was
 * registered with setWebhook.
 *
 * Telegram echoes the secret in the X-Telegram-Bot-Api-Secret-Token header on
 * every update. As a fallback (and because some proxies strip custom headers) a
 * `secret` query param is also accepted, matching Telegram's standard practice
 * of putting the secret in the webhook URL. Comparison is constant-time.
 *
 * If no secret is configured, the endpoint is closed (fail-safe): TechBot must
 * have its webhook secret set before it can receive updates.
 */
class TelegramWebhookFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $response = service('response');
        $settings = service('techBotSettings');

        $expected = $settings->webhookSecret();
        if ($expected === '') {
            return $this->deny($response, 503, 'Webhook no configurado.');
        }

        $provided = (string) ($request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token')
            ?: $request->getGet('secret'));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return $this->deny($response, 403, 'Secreto inválido.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }

    private function deny(ResponseInterface $response, int $code, string $message): ResponseInterface
    {
        return $response->setStatusCode($code)->setJSON(['status' => 'error', 'message' => $message]);
    }
}
