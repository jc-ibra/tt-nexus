<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;
use App\Modules\Core\Models\ModuleModel;

class Roles extends BaseController
{
    public function index(): string
    {
        $data = service('roleService')->paginate(20);

        return view('App\Modules\Core\Views\admin\roles\index', array_merge($data, [
            'pageTitle' => 'Roles',
        ]));
    }

    public function new(): string
    {
        return view('App\Modules\Core\Views\admin\roles\form', [
            'pageTitle' => 'Nuevo rol',
            'role'      => null,
            'modules'   => (new ModuleModel())->getAllActive(),
        ]);
    }

    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'description', 'status', 'module_ids']);
        $result = service('roleService')->create($data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('admin.roles.index'))->with('success', 'Rol creado correctamente.');
    }

    public function show(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(route_to('admin.roles.edit', $id));
    }

    public function edit(int $id): string
    {
        $role = service('roleService')->findById($id);

        if ($role === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Core\Views\admin\roles\form', [
            'pageTitle' => 'Editar rol',
            'role'      => $role,
            'modules'   => (new ModuleModel())->getAllActive(),
        ]);
    }

    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'description', 'status', 'module_ids']);
        $result = service('roleService')->update($id, $data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('admin.roles.index'))->with('success', 'Rol actualizado.');
    }

    public function destroy(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('roleService')->destroy($id);

        if (! $result->success) {
            return redirect()->back()->with('error', $result->message);
        }

        return redirect()->to(route_to('admin.roles.index'))->with('success', 'Rol eliminado.');
    }
}
