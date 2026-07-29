<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Controllers;

use App\Controllers\BaseController;
use App\Modules\TechBot\Models\ActivityLogModel;
use App\Modules\TechBot\Models\TelegramLinkModel;
use App\Modules\TechBot\Services\TemplateService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * TechBot admin panel (supervisors / SuperAdmin). The technician-facing
 * interface is Telegram; this is only the web administration surface (spec §12).
 */
class TechBot extends BaseController
{
    // --------------------------------------------------------------
    // Dashboard
    // --------------------------------------------------------------

    public function index(): string
    {
        $links    = new TelegramLinkModel();
        $activity = new ActivityLogModel();

        $today = date('Y-m-d 00:00:00');
        $week  = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $month = date('Y-m-01 00:00:00');

        return view('App\Modules\TechBot\Views\dashboard', [
            'pageTitle'      => 'TechBot',
            'activeCount'    => $links->countActive(),
            'inactiveCount'  => $links->countInactive(),
            'actionsToday'   => $activity->countSince($today),
            'actionsWeek'    => $activity->countSince($week),
            'actionsMonth'   => $activity->countSince($month),
            'errorsToday'    => $activity->countErrorsSince($today),
            'recentErrors'   => $activity->recentErrors(6),
            'recentActivity' => $activity->recentWithEmployee([], 10),
            'botReady'       => service('techBotSettings')->botReady(),
        ]);
    }

    // --------------------------------------------------------------
    // Linked technicians
    // --------------------------------------------------------------

    public function links(): string
    {
        $links    = new TelegramLinkModel();
        $lastSeen = (new ActivityLogModel())->lastActivityByEmployee();

        return view('App\Modules\TechBot\Views\links\index', [
            'pageTitle' => 'Técnicos vinculados · TechBot',
            'links'     => $links->listWithEmployee(),
            'lastSeen'  => $lastSeen,
        ]);
    }

    public function showLink(int $id): string|ResponseInterface
    {
        $link = (new TelegramLinkModel())->findWithEmployee($id);
        if ($link === null) {
            return redirect()->to(route_to('techbot.links'))->with('error', 'Vinculación no encontrada.');
        }

        return view('App\Modules\TechBot\Views\links\show', [
            'pageTitle' => 'Vinculación · TechBot',
            'link'      => $link,
            'activity'  => (new ActivityLogModel())->recentWithEmployee(['employee_id' => (int) $link['employee_id']], 30),
        ]);
    }

    public function deactivateLink(int $id): ResponseInterface
    {
        (new TelegramLinkModel())->setStatus($id, 'inactive');
        return redirect()->to(route_to('techbot.links'))->with('success', 'Vinculación desactivada.');
    }

    public function activateLink(int $id): ResponseInterface
    {
        (new TelegramLinkModel())->setStatus($id, 'active');
        return redirect()->to(route_to('techbot.links'))->with('success', 'Vinculación reactivada.');
    }

    // --------------------------------------------------------------
    // Activity log
    // --------------------------------------------------------------

    public function activity(): string
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

        return view('App\Modules\TechBot\Views\activity\index', [
            'pageTitle' => 'Actividad · TechBot',
            'rows'      => (new ActivityLogModel())->recentWithEmployee($filters, 200),
            'filters'   => $filters,
            'actions'   => TemplateService::ACTIONS,
        ]);
    }

    public function showActivity(int $id): string|ResponseInterface
    {
        $row = (new ActivityLogModel())->findWithEmployee($id);
        if ($row === null) {
            return redirect()->to(route_to('techbot.activity'))->with('error', 'Registro no encontrado.');
        }

        return view('App\Modules\TechBot\Views\activity\show', [
            'pageTitle' => 'Detalle de actividad · TechBot',
            'row'       => $row,
        ]);
    }

    // --------------------------------------------------------------
    // Settings
    // --------------------------------------------------------------

    public function settings(): string
    {
        $settings = service('techBotSettings');

        return view('App\Modules\TechBot\Views\settings\index', [
            'pageTitle'       => 'Configuración · TechBot',
            'settings'        => $settings->all(),
            'hasToken'        => $settings->hasBotToken(),
            'hasSecret'       => $settings->hasWebhookSecret(),
            'aiSystemPrompt'  => $settings->aiSystemPrompt(),
            'webhookUrl'      => rtrim(base_url(), '/') . '/api/v1/techbot/webhook',
            'aiConfigured'    => service('serviceDeskSettings')->aiHasApiKey(),
        ]);
    }

    public function saveSettings(): ResponseInterface
    {
        $result = service('techBotSettings')->save($this->request->getPost());
        return redirect()->to(route_to('techbot.settings'))
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Validates the stored bot token with a getMe call.
     */
    public function testConnection(): ResponseInterface
    {
        $resp = service('techBotTelegramApi')->getMe();
        if (! $resp['ok']) {
            return redirect()->to(route_to('techbot.settings'))
                ->with('error', 'No se pudo conectar con Telegram: ' . $resp['error']);
        }
        $username = (string) ($resp['result']['username'] ?? '');
        return redirect()->to(route_to('techbot.settings'))
            ->with('success', 'Conexión exitosa con el bot @' . $username . '.');
    }

    /**
     * Registers the webhook URL (and secret) with Telegram via setWebhook.
     */
    public function registerWebhook(): ResponseInterface
    {
        $settings = service('techBotSettings');
        if (! $settings->hasBotToken()) {
            return redirect()->to(route_to('techbot.settings'))->with('error', 'Configura primero el token del bot.');
        }

        $secret = $settings->ensureWebhookSecret();
        if ($secret === '') {
            return redirect()->to(route_to('techbot.settings'))
                ->with('error', 'No se pudo generar el secreto del webhook (falta encryption.key).');
        }

        $url  = rtrim(base_url(), '/') . '/api/v1/techbot/webhook';
        $resp = service('techBotTelegramApi')->setWebhook($url, $secret);
        if (! $resp['ok']) {
            return redirect()->to(route_to('techbot.settings'))
                ->with('error', 'Telegram rechazó el webhook: ' . $resp['error']);
        }

        return redirect()->to(route_to('techbot.settings'))
            ->with('success', 'Webhook registrado en Telegram: ' . $url);
    }
}
