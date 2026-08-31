<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;

class Auth extends BaseController
{
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

        // Bypass de MFA solo para desarrollo local. Doble candado: exige
        // ENVIRONMENT === 'development' Y el flag MFA_DEV_BYPASS en .env, de
        // modo que copiar el flag a un .env de producción no tenga efecto.
        if (ENVIRONMENT === 'development'
            && filter_var(env('MFA_DEV_BYPASS'), FILTER_VALIDATE_BOOLEAN)) {
            service('authService')->completeMfa();

            return redirect()->to(route_to('dashboard'));
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

    public function switchRole(): \CodeIgniter\HTTP\RedirectResponse
    {
        $roleId = (int) $this->request->getPost('role_id');

        if (! service('access')->setActiveRole($roleId)) {
            return redirect()->back()->with('errors', ['Rol no válido.']);
        }

        return redirect()->to(route_to('dashboard'))->with('success', 'Rol activo actualizado.');
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

    /**
     * Landing page of an invitation link: the invited person sets their name
     * and password here. No session exists yet, so the route is public and the
     * token is the only credential.
     */
    public function invitation(string $token): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $resolved = service('invitationService')->resolve($token);

        if ($resolved === null) {
            return redirect()->to(route_to('login'))->with(
                'error',
                'El enlace de invitación es inválido, ya fue usado o expiró. Pide al administrador que te lo reenvíe.'
            );
        }

        return view('App\Modules\Core\Views\auth\invitation', [
            'pageTitle' => 'Activa tu cuenta',
            'token'     => $token,
            'email'     => $resolved['user']['email'],
            'name'      => $resolved['user']['name'],
        ]);
    }

    public function acceptInvitation(string $token): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('invitationService')->accept(
            $token,
            (string) $this->request->getPost('name'),
            (string) $this->request->getPost('password'),
            (string) $this->request->getPost('password_confirm')
        );

        if (! $result->success) {
            // Explicit target instead of back(): there is no session yet and a
            // missing referer would drop the person on the home page.
            return redirect()->to(route_to('invitation.show', $token))
                ->withInput()->with('errors', $result->errors);
        }

        // The session is already open: AuthFilter routes them to the MFA setup.
        return redirect()->to(site_url('mfa/setup'))->with('success', $result->message);
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
