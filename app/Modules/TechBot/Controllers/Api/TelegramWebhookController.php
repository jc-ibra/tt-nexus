<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public Telegram webhook endpoint. The techbot_webhook filter has already
 * validated the shared secret before this runs.
 *
 * Telegram expects a fast 2xx (spec §16). We process synchronously (GLPI is
 * quick), but ALWAYS return 200 — even on internal failure — so Telegram does
 * not spin on retries; failures are logged and surfaced in the activity log.
 */
class TelegramWebhookController extends Controller
{
    public function handle(): ResponseInterface
    {
        $raw    = $this->request->getBody() ?? '';
        $update = json_decode((string) $raw, true);

        if (is_array($update)) {
            try {
                service('techBotWebhook')->process($update);
            } catch (\Throwable $e) {
                log_message('error', '[TechBot][Webhook] unhandled: ' . $e->getMessage());
            }
        }

        return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
    }
}
