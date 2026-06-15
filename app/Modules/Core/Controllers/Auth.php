<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;

class Auth extends BaseController
{
    public function redirectHome(): \CodeIgniter\HTTP\RedirectResponse
    {
        // Toolbar background requests hit this route — skip session to avoid lock contention
        $req = service('request');
        if ($req->getGet('debugbar') !== null || $req->getGet('debugbar_time') !== null) {
            return redirect()->to(route_to('login'));
        }

        return session()->get('user_id')
            ? redirect()->to(route_to('dashboard'))
            : redirect()->to(route_to('login'));
    }

    public function login(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $session = session();

        if ($session->get('user_id')) {
            if ($session->get('mfa_verified')) {
                return redirect()->to(route_to('dashboard'));
            }
            return $session->get('mfa_enabled')
                ? redirect()->to(site_url('mfa/verify'))
                : redirect()->to(site_url('mfa/setup'));
        }

        return view('App\Modules\Core\Views\auth\login', ['pageTitle' => 'Iniciar sesión']);
    }

    public function attempt(): \CodeIgniter\HTTP\RedirectResponse
    {
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $result = service('authService')->attempt(
            (string) $email,
            (string) $password,
            (string) $this->request->getIPAddress()
        );

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', [$result->message]);
        }

        return $result->data['mfa_enabled']
            ? redirect()->to(site_url('mfa/verify'))
            : redirect()->to(site_url('mfa/setup'));
    }

    public function logout(): \CodeIgniter\HTTP\RedirectResponse
    {
        session()->destroy();

        return redirect()->to(route_to('login'))->with('success', 'Sesión cerrada.');
    }

    public function forgotPassword(): string
    {
        return view('App\Modules\Core\Views\auth\forgot_password', ['pageTitle' => 'Restablecer contraseña']);
    }

    public function sendResetLink(): \CodeIgniter\HTTP\RedirectResponse
    {
        $email  = (string) $this->request->getPost('email');
        $result = service('authService')->sendResetLink($email);

        return redirect()->back()->with('success', $result->message);
    }

    public function resetForm(string $token): string
    {
        return view('App\Modules\Core\Views\auth\reset_password', [
            'pageTitle' => 'Nueva contraseña',
            'token'     => $token,
        ]);
    }

    public function resetPassword(string $token): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('authService')->resetPassword(
            $token,
            (string) $this->request->getPost('password'),
            (string) $this->request->getPost('password_confirm')
        );

        if (! $result->success) {
            return redirect()->back()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('login'))->with('success', $result->message);
    }
}
