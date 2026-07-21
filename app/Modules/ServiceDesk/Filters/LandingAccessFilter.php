<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for the PUBLIC self-service landing (route group /soporte) — the only
 * public page Nexus exposes. The landing is served by Nexus, so its chat/ticket
 * POSTs are SAME-ORIGIN. This filter enforces, in order:
 *
 *   1. landing enabled (kill-switch);
 *   2. per-IP rate limit on POSTs only (the AI calls are the costly/abusable
 *      surface; the page GET stays cheap and is not throttled so refreshes and
 *      crawlers cannot lock a user out);
 *   3. on POST: a valid landing site key (rendered into the page) AND a
 *      same-origin Origin/Referer, so only our own landing page can drive the
 *      chat/ticket endpoints.
 *
 * A disabled/limited GET returns a friendly HTML notice; POSTs return JSON.
 */
class LandingAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $response = service('response');
        $settings = service('serviceDeskSettings');
        $method   = strtolower($request->getMethod());
        $isPost   = $method === 'post';

        // Same-origin page; answer any preflight cheaply.
        if ($method === 'options') {
            return $response->setStatusCode(204);
        }

        if (! $settings->landingEnabled()) {
            return $isPost
                ? $this->json($response, 403, 'La mesa de ayuda en línea no está disponible en este momento.')
                : $this->page($response, 403, 'No disponible', 'La mesa de ayuda en línea no está disponible en este momento. Intenta más tarde.');
        }

        // Rate limit the costly POST actions per IP.
        if ($isPost) {
            $limit = $settings->landingRateLimitPerHour();
            if ($limit > 0) {
                $throttler = service('throttler');
                if ($throttler->check('sdlanding_' . md5($request->getIPAddress()), $limit, HOUR) === false) {
                    return $this->json($response, 429, 'Demasiadas solicitudes. Espera un momento e intenta de nuevo.');
                }
            }

            // Site key (query, POST or header).
            $key = (string) ($request->getGet('key')
                ?: $request->getPost('key')
                ?: $request->getHeaderLine('X-Widget-Key'));
            if ($key === '' || ! hash_equals($settings->landingSiteKey(), $key)) {
                return $this->json($response, 403, 'Sesión no válida. Recarga la página.');
            }

            // Same-origin: only our own landing page may call these endpoints.
            $reqOrigin = $this->originFromUrl($request->getHeaderLine('Origin') ?: $request->getHeaderLine('Referer'));
            $ourOrigin = $this->originFromUrl(base_url());
            if ($reqOrigin !== '' && $reqOrigin !== $ourOrigin) {
                return $this->json($response, 403, 'Origen no autorizado.');
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }

    private function json(ResponseInterface $response, int $code, string $message): ResponseInterface
    {
        return $response->setStatusCode($code)->setJSON(['status' => 'error', 'message' => $message]);
    }

    /** Minimal self-contained HTML notice for a denied page load. */
    private function page(ResponseInterface $response, int $code, string $title, string $message): ResponseInterface
    {
        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . esc($title) . '</title>'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
            . 'font:15px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f8fb;color:#1f2733}'
            . '.box{max-width:420px;padding:32px;text-align:center}h1{font-size:18px;margin:0 0 8px;color:#1773C8}'
            . 'p{margin:0;color:#66707d}</style></head><body><div class="box">'
            . '<h1>' . esc($title) . '</h1><p>' . esc($message) . '</p></div></body></html>';

        return $response->setStatusCode($code)->setContentType('text/html; charset=UTF-8')->setBody($html);
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
