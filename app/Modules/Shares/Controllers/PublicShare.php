<?php

declare(strict_types=1);

namespace App\Modules\Shares\Controllers;

use App\Controllers\BaseController;
use App\Modules\Shares\Models\ShareFileModel;
use App\Modules\Shares\Services\ShareService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PUBLIC (no auth). Serves a shared HTML by its unguessable token.
 * Unknown tokens answer 404; revoked, expired or exhausted links answer 410
 * with the same message so the cause is not revealed.
 */
class PublicShare extends BaseController
{
    private const HITS_PER_MINUTE      = 120;
    private const PASSWORD_ATTEMPTS    = 10;
    private const PASSWORD_WINDOW_SECS = 900;

    private function svc(): ShareService
    {
        return new ShareService();
    }

    public function show(string $token): ResponseInterface
    {
        if (service('throttler')->check('shares_' . md5($this->request->getIPAddress()), self::HITS_PER_MINUTE, MINUTE) === false) {
            return $this->page('unavailable', 429, ['title' => 'Demasiadas solicitudes', 'message' => 'Espera un momento e intenta de nuevo.']);
        }

        $link = $this->svc()->resolvePublic($token);
        if ($link === null) {
            return $this->page('unavailable', 404, ['title' => 'Enlace no encontrado', 'message' => 'Revisa que la dirección esté completa.']);
        }
        if ($link['status'] !== ShareService::STATUS_ACTIVE) {
            return $this->gone();
        }
        if (! empty($link['password_hash']) && ! $this->isUnlocked($link)) {
            return $this->page('password', 200, ['token' => $token, 'title' => $link['file_title'], 'error' => null]);
        }

        return $this->serve($link);
    }

    public function unlock(string $token): ResponseInterface
    {
        $link = $this->svc()->resolvePublic($token);
        if ($link === null) {
            return $this->page('unavailable', 404, ['title' => 'Enlace no encontrado', 'message' => 'Revisa que la dirección esté completa.']);
        }
        if ($link['status'] !== ShareService::STATUS_ACTIVE) {
            return $this->gone();
        }
        if (empty($link['password_hash'])) {
            return redirect()->to(site_url('s/' . $token));
        }

        $key = 'sharespw_' . md5($this->request->getIPAddress() . $token);
        if (service('throttler')->check($key, self::PASSWORD_ATTEMPTS, self::PASSWORD_WINDOW_SECS) === false) {
            return $this->page('password', 429, [
                'token' => $token, 'title' => $link['file_title'],
                'error' => 'Demasiados intentos. Espera unos minutos e intenta de nuevo.',
            ]);
        }

        if (! password_verify((string) $this->request->getPost('password'), $link['password_hash'])) {
            return $this->page('password', 401, [
                'token' => $token, 'title' => $link['file_title'], 'error' => 'La contraseña no es correcta.',
            ]);
        }

        $unlocked                   = (array) session()->get('shares_unlocked');
        $unlocked[(int) $link['id']] = $this->svc()->unlockMarker($link);
        session()->set('shares_unlocked', $unlocked);

        return redirect()->to(site_url('s/' . $token));
    }

    private function isUnlocked(array $link): bool
    {
        $unlocked = (array) session()->get('shares_unlocked');

        return isset($unlocked[(int) $link['id']])
            && hash_equals($this->svc()->unlockMarker($link), (string) $unlocked[(int) $link['id']]);
    }

    private function serve(array $link): ResponseInterface
    {
        // The count and the validity check are one atomic UPDATE: a link capped
        // at N views can never be served an N+1st time under concurrency.
        if (! $this->svc()->registerView((int) $link['id'])) {
            return $this->gone();
        }

        $file = (new ShareFileModel())->find((int) $link['file_id']);
        if ($file === null) {
            return $this->gone();
        }

        $this->svc()->stream($file, true);
    }

    private function gone(): ResponseInterface
    {
        return $this->page('unavailable', 410, [
            'title'   => 'Este enlace ya no está disponible',
            'message' => 'Pide a quien te lo compartió que te envíe uno nuevo.',
        ]);
    }

    private function page(string $view, int $status, array $data): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(view('App\Modules\Shares\Views\public\\' . $view, $data));
    }
}
