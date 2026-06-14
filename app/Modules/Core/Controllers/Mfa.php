<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;
use App\Modules\Core\Models\UserModel;

class Mfa extends BaseController
{
    public function setup(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $session = session();

        if (! $session->get('user_id')) {
            return redirect()->to(route_to('login'));
        }

        // Sync mfa_enabled from DB in case session predates this feature
        if ($session->get('mfa_enabled') === null) {
            $user = model(UserModel::class)->find($session->get('user_id'));
            $session->set('mfa_enabled', (bool) ($user['mfa_enabled'] ?? false));
        }

        if ($session->get('mfa_enabled')) {
            return redirect()->to(site_url('mfa/verify'));
        }

        // Generate a pending secret and keep it in session until the user confirms it
        if (! $session->get('mfa_pending_secret')) {
            $session->set('mfa_pending_secret', service('authService')->generateMfaSecret());
        }

        $secret     = $session->get('mfa_pending_secret');
        $authSvc    = service('authService');
        $qrDataUri  = $authSvc->getMfaQrCodeDataUri((string) $session->get('user_email'), $secret);

        return view('App\Modules\Core\Views\auth\mfa_setup', [
            'pageTitle' => 'Configura tu autenticador',
            'qrDataUri' => $qrDataUri,
            'secret'    => $secret,
        ]);
    }

    public function activate(): \CodeIgniter\HTTP\RedirectResponse
    {
        $session = session();

        if (! $session->get('user_id')) {
            return redirect()->to(route_to('login'));
        }

        $pendingSecret = $session->get('mfa_pending_secret');
        if (! $pendingSecret) {
            return redirect()->to(site_url('mfa/setup'));
        }

        $throttler = service('throttler');
        $key       = 'mfa_activate_' . $session->get('user_id');
        if ($throttler->check($key, 5, 60) === false) {
            return redirect()->to(site_url('mfa/setup'))
                ->with('errors', ['Demasiados intentos. Espera ' . $throttler->getTokentime() . ' segundos.']);
        }

        $code = (string) $this->request->getPost('code');
        if (! service('authService')->verifyTotp($pendingSecret, $code)) {
            return redirect()->to(site_url('mfa/setup'))
                ->with('errors', ['Código incorrecto. Escanea el QR de nuevo e ingresa el código actual.']);
        }

        $throttler->remove($key);

        $userId = (int) $session->get('user_id');
        service('authService')->enableMfa($userId, $pendingSecret);

        $session->remove('mfa_pending_secret');
        $session->set('mfa_enabled', true);
        service('authService')->completeMfa();

        return redirect()->to(route_to('dashboard'))->with('success', 'Autenticación en dos pasos activada correctamente.');
    }

    public function verify(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $session = session();

        if (! $session->get('user_id')) {
            return redirect()->to(route_to('login'));
        }

        // Sync mfa_enabled from DB in case session predates this feature
        if ($session->get('mfa_enabled') === null) {
            $user = model(UserModel::class)->find($session->get('user_id'));
            $session->set('mfa_enabled', (bool) ($user['mfa_enabled'] ?? false));
        }

        if (! $session->get('mfa_enabled')) {
            return redirect()->to(site_url('mfa/setup'));
        }

        if ($session->get('mfa_verified')) {
            return redirect()->to(route_to('dashboard'));
        }

        return view('App\Modules\Core\Views\auth\mfa_verify', [
            'pageTitle' => 'Verificación en dos pasos',
        ]);
    }

    public function check(): \CodeIgniter\HTTP\RedirectResponse
    {
        $session = session();

        if (! $session->get('user_id')) {
            return redirect()->to(route_to('login'));
        }

        $throttler = service('throttler');
        $key       = 'mfa_verify_' . $session->get('user_id');
        if ($throttler->check($key, 5, 60) === false) {
            return redirect()->to(site_url('mfa/verify'))
                ->with('errors', ['Demasiados intentos. Espera ' . $throttler->getTokentime() . ' segundos.']);
        }

        $userId = (int) $session->get('user_id');
        $user   = model(UserModel::class)->find($userId);

        if ($user === null || empty($user['mfa_secret'])) {
            return redirect()->to(route_to('login'));
        }

        $code = (string) $this->request->getPost('code');
        if (! service('authService')->verifyTotp($user['mfa_secret'], $code)) {
            return redirect()->to(site_url('mfa/verify'))
                ->with('errors', ['Código incorrecto. Por favor intenta de nuevo.']);
        }

        $throttler->remove($key);
        service('authService')->completeMfa();

        return redirect()->to(route_to('dashboard'));
    }
}
