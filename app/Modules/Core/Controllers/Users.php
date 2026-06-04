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
        $data   = $this->request->getPost(['name', 'email', 'password', 'status', 'role_ids']);
        $result = service('userService')->create($data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('admin.users.index'))->with('success', 'Usuario creado correctamente.');
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
        $data   = $this->request->getPost(['name', 'email', 'password', 'status', 'role_ids']);
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
