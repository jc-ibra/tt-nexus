<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for the public, unauthenticated self-service widget endpoints.
 *
 * The widget is served inside an iframe hosted by Nexus, so its chat/ticket
 * fetches are SAME-ORIGIN (no CSRF/CORS friction). The cross-origin surface is
 * only the initial embed.js/frame load from the intranet. This filter enforces,
 * in order:
 *   1. widget enabled;
 *   2. valid public site key (query, POST or X-Widget-Key header);
 *   3. embed origin allowlist — same-origin (iframe calling home) is always
 *      allowed; any other origin/referer must be whitelisted by the admin;
 *   4. per-IP rate limit (abuse / AI cost guard).
 * It also answers CORS preflight and echoes the allow-origin header.
 */
class WidgetAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $response = service('response');
        $settings = service('serviceDeskSettings');

        $reqOrigin = $this->originFromUrl($request->getHeaderLine('Origin'));
        $ourOrigin = $this->originFromUrl(base_url());

        // CORS preflight — answer before any auth work.
        if (strtolower($request->getMethod()) === 'options') {
            return $this->cors($response->setStatusCode(204), $reqOrigin);
        }

        if (! $settings->widgetEnabled()) {
            return $this->deny($response, 403, 'El widget está deshabilitado.');
        }

        // Site key.
        $key = (string) ($request->getGet('key')
            ?: $request->getPost('key')
            ?: $request->getHeaderLine('X-Widget-Key'));
        if ($key === '' || ! hash_equals($settings->widgetSiteKey(), $key)) {
            return $this->deny($response, 403, 'Clave del widget inválida.');
        }

        // Embed-origin allowlist. Same-origin (iframe -> home, or missing origin
        // on classic script/iframe GETs) is always fine; anything else must be
        // whitelisted. Use the Referer when there is no Origin header.
        $embedOrigin = $reqOrigin !== '' ? $reqOrigin : $this->originFromUrl($request->getHeaderLine('Referer'));
        $sameOrigin  = $embedOrigin === '' || $embedOrigin === $ourOrigin;
        if (! $sameOrigin) {
            $allowed = array_map(fn($o) => $this->originFromUrl($o), $settings->widgetAllowedOrigins());
            if (! in_array($embedOrigin, $allowed, true)) {
                return $this->deny($response, 403, 'Origen no autorizado para el widget.');
            }
        }

        // Per-IP rate limit.
        $limit = $settings->widgetRateLimitPerHour();
        if ($limit > 0) {
            $throttler = service('throttler');
            if ($throttler->check('sdwidget_' . md5($request->getIPAddress()), $limit, HOUR) === false) {
                return $this->deny($response, 429, 'Demasiadas solicitudes. Espera un momento e intenta de nuevo.');
            }
        }

        // Echo the allow-origin so cross-origin fetches (if any) succeed.
        if ($reqOrigin !== '') {
            $response->setHeader('Access-Control-Allow-Origin', $reqOrigin);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }

    private function cors(ResponseInterface $response, string $origin): ResponseInterface
    {
        return $response
            ->setHeader('Access-Control-Allow-Origin', $origin !== '' ? $origin : '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Widget-Key')
            ->setHeader('Access-Control-Max-Age', '600');
    }

    private function deny(ResponseInterface $response, int $code, string $message): ResponseInterface
    {
        return $response
            ->setStatusCode($code)
            ->setJSON(['status' => 'error', 'message' => $message]);
    }

    /** Normalizes any URL/Origin into origin form: scheme://host[:port], lowercased. */
    private function originFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $p = parse_url($url);
        if (empty($p['host'])) {
            return '';
        }
        $origin = strtolower(($p['scheme'] ?? 'https') . '://' . $p['host']);
        if (! empty($p['port'])) {
            $origin .= ':' . $p['port'];
        }
        return $origin;
    }
}
