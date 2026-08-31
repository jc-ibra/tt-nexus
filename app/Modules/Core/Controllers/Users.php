<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;
use App\Modules\Core\Models\RoleModel;

class Users extends BaseController
{
    public function index(): string
    {
        $data = service('userService')->paginate(20);

        return view('App\Modules\Core\Views\admin\users\index', array_merge($data, [
            'pageTitle' => 'Usuarios',
        ]));
    }

    public function new(): string
    {
        return view('App\Modules\Core\Views\admin\users\form', [
            'pageTitle' => 'Nuevo usuario',
            'user'      => null,
            'roles'     => (new RoleModel())->getAllActive(),
        ]);
    }

    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'email', 'password', 'status', 'role_ids', 'glpi_user_id', 'auth_method']);
        $result = service('userService')->create($data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        if (empty($result->data['by_invitation'])) {
            return redirect()->to(route_to('admin.users.index'))->with('success', 'Usuario creado correctamente.');
        }

        // The account exists but is unusable until the invitation is redeemed;
        // a failed send is surfaced as a warning, not as a silent success, and
        // the account stays in the list ready to be resent.
        $sent = service('invitationService')->send((int) $result->data['id']);

        if (! $sent->success) {
            return redirect()->to(route_to('admin.users.show', $result->data['id']))
                ->with('warning', $sent->message);
        }

        return redirect()->to(route_to('admin.users.index'))->with('success', $sent->message);
    }

    /**
     * Sends the invitation again. Issuing a new token kills the previous link.
     */
    public function invite(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('invitationService')->send($id);

        return $result->success
            ? redirect()->back()->with('success', $result->message)
            : redirect()->back()->with('error', $result->message);
    }

    public function revokeInvitation(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('invitationService')->revoke($id);

        return $result->success
            ? redirect()->back()->with('success', $result->message)
            : redirect()->back()->with('error', $result->message);
    }

    /**
     * Unbinds the authenticator so the user configures a new one at the next
     * sign in. For lost or replaced phones.
     */
    public function resetMfa(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $user = service('userService')->findById($id);

        if ($user === null) {
            return redirect()->back()->with('error', 'Usuario no encontrado.');
        }

        service('authService')->resetMfa($id);
        log_message('info', '[Auth] MFA reset for user_id=' . $id . ' by user_id=' . session()->get('user_id'));

        // Not escaped here: the flash partial escapes it when rendering.
        return redirect()->back()->with(
            'success',
            'Verificación en dos pasos reiniciada. ' . $user['name'] . ' configurará un nuevo autenticador al entrar.'
        );
    }

    public function show(int $id): string
    {
        $user = service('userService')->findById($id);

        if ($user === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Core\Views\admin\users\show', [
            'pageTitle' => $user['name'],
            'user'      => $user,
        ]);
    }

    public function edit(int $id): string
    {
        $user = service('userService')->findById($id);

        if ($user === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Core\Views\admin\users\form', [
            'pageTitle' => 'Editar usuario',
            'user'      => $user,
            'roles'     => (new RoleModel())->getAllActive(),
        ]);
    }

    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'email', 'password', 'status', 'role_ids', 'glpi_user_id']);
        $result = service('userService')->update($id, $data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('admin.users.index'))->with('success', 'Usuario actualizado.');
    }

    public function destroy(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('userService')->destroy($id);

        if (! $result->success) {
            return redirect()->back()->with('error', $result->message);
        }

        return redirect()->to(route_to('admin.users.index'))->with('success', 'Usuario eliminado.');
    }
}
