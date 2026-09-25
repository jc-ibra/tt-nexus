<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for the PUBLIC CSAT survey (route group /survey/{token}). Structurally
 * a copy of ServiceDesk's LandingAccessFilter, adapted to a token-carried
 * credential instead of a site key:
 *
 *   1. survey enabled (kill-switch), checked on both GET and POST;
 *   2. per-IP rate limit on POST only (the GET must stay cheap and
 *      unthrottled — Outlook Safe Links and mail-gateway scanners open the
 *      link before the human does, and must never lock the real recipient out);
 *   3. on POST: a same-origin Origin/Referer. No site key: the 64-hex token in
 *      the URL already is the credential, and a second secret adds surface
 *      without adding security (whoever can forge the POST already has the
 *      token and could legitimately answer with it).
 *
 * A disabled/limited GET returns a friendly HTML notice; POSTs return JSON.
 */
class SurveyAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $response = $this->responseService();
        $settings = service('mailDispatchSettings');
        $method   = strtolower($request->getMethod());
        $isPost   = $method === 'post';

        if ($method === 'options') {
            return $response->setStatusCode(204);
        }

        if (! $settings->surveyEnabled()) {
            return $isPost
                ? $this->json($response, 403, 'Este enlace ya no está disponible.')
                : $this->page($response, 403, 'No disponible', 'Este enlace ya no está disponible. Si necesitas ayuda, responde el correo de la mesa de ayuda.');
        }

        if ($isPost) {
            $limit = $settings->surveyRateLimitPerHour();
            if ($limit > 0) {
                $throttler = service('throttler');
                if ($throttler->check('mdsurvey_' . md5($request->getIPAddress()), $limit, HOUR) === false) {
                    return $this->json($response, 429, 'Demasiadas solicitudes. Espera un momento e intenta de nuevo.');
                }
            }

            // Same-origin: only our own survey page may submit an answer. A
            // missing Origin/Referer (e.g. a stricter no-referrer policy some
            // browsers apply) is tolerated, same as the landing filter — the
            // token itself is what authorizes the POST.
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

    private function responseService(): ResponseInterface
    {
        return service('response');
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
            . '<meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">'
            . '<link rel="icon" type="image/png" href="' . esc(base_url('img/tt-icon.png'), 'attr') . '">'
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
