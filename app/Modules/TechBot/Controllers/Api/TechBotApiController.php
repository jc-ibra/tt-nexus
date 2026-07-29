<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\TechBot\Models\ActivityLogModel;
use App\Modules\TechBot\Models\TelegramLinkModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API mirror of the TechBot admin panel (spec §12.2). Bearer-authenticated and
 * gated by module access. Secrets are never returned.
 */
class TechBotApiController extends BaseApiController
{
    public function listLinks(): ResponseInterface
    {
        return $this->success((new TelegramLinkModel())->listWithEmployee());
    }

    public function showLink(int $id): ResponseInterface
    {
        $link = (new TelegramLinkModel())->findWithEmployee($id);
        if ($link === null) {
            return $this->notFound('Vinculación no encontrada.');
        }
        return $this->success($link);
    }

    public function deactivateLink(int $id): ResponseInterface
    {
        $model = new TelegramLinkModel();
        if ($model->find($id) === null) {
            return $this->notFound('Vinculación no encontrada.');
        }
        $model->setStatus($id, 'inactive');
        return $this->success(['id' => $id, 'status' => 'inactive']);
    }

    public function activateLink(int $id): ResponseInterface
    {
        $model = new TelegramLinkModel();
        if ($model->find($id) === null) {
            return $this->notFound('Vinculación no encontrada.');
        }
        $model->setStatus($id, 'active');
        return $this->success(['id' => $id, 'status' => 'active']);
    }

    public function activity(): ResponseInterface
    {
        $filters = [
            'employee_id'    => (int) ($this->request->getGet('employee_id') ?? 0),
            'glpi_ticket_id' => (int) ($this->request->getGet('ticket') ?? 0),
            'action'         => trim((string) $this->request->getGet('action')),
            'result'         => trim((string) $this->request->getGet('result')),
            'from'           => trim((string) $this->request->getGet('from')),
            'to'             => trim((string) $this->request->getGet('to')),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== '' && $v !== 0);

        return $this->success((new ActivityLogModel())->recentWithEmployee($filters, 200));
    }

    /** Settings WITHOUT secrets (token/secret values are stripped). */
    public function getSettings(): ResponseInterface
    {
        $settings = service('techBotSettings');
        $all      = $settings->all();
        unset($all['telegram_bot_token'], $all['telegram_webhook_secret']);
        $all['has_bot_token']      = $settings->hasBotToken();
        $all['has_webhook_secret'] = $settings->hasWebhookSecret();

        return $this->success($all);
    }

    public function updateSettings(): ResponseInterface
    {
        $input  = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $result = service('techBotSettings')->save(is_array($input) ? $input : []);
        return $result->success
            ? $this->success(['saved' => true, 'message' => $result->message])
            : $this->error($result->message);
    }

    public function testConnection(): ResponseInterface
    {
        $resp = service('techBotTelegramApi')->getMe();
        return $resp['ok']
            ? $this->success(['bot' => $resp['result']])
            : $this->error('No se pudo conectar con Telegram: ' . $resp['error']);
    }

    public function registerWebhook(): ResponseInterface
    {
        $settings = service('techBotSettings');
        if (! $settings->hasBotToken()) {
            return $this->error('Configura primero el token del bot.');
        }
        $secret = $settings->ensureWebhookSecret();
        if ($secret === '') {
            return $this->error('No se pudo generar el secreto del webhook (falta encryption.key).');
        }

        $url  = rtrim(base_url(), '/') . '/api/v1/techbot/webhook';
        $resp = service('techBotTelegramApi')->setWebhook($url, $secret);
        return $resp['ok']
            ? $this->success(['webhook' => $url])
            : $this->error('Telegram rechazó el webhook: ' . $resp['error']);
    }
}
