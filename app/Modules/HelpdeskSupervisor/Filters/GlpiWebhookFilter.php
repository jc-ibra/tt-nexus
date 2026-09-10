<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public GLPI webhook auth. Fail-closed when disabled or secret unset.
 * Accepts X-Helpdesk-Webhook-Secret header or ?secret= query (proxies).
 */
class GlpiWebhookFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $response = service('response');
        $settings = service('helpdeskSupervisorSettings');

        if (! $settings->webhookEnabled()) {
            return $this->deny($response, 503, 'Webhook desactivado.');
        }

        $expected = $settings->webhookSecret();
        if ($expected === '') {
            return $this->deny($response, 503, 'Webhook no configurado (falta secreto).');
        }

        $provided = (string) ($request->getHeaderLine('X-Helpdesk-Webhook-Secret')
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
