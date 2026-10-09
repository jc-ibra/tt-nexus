<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

/** SuperAdmin: choose which roles may replace the presentation. */
class PresentationsAdmin extends BaseController
{
    public function permissions(): string
    {
        $db   = \Config\Database::connect();
        $perm = $db->table('core_permissions')->where('key', 'presentations.upload')->get()->getRowArray();
        $mod  = $db->table('core_modules')->where('key', 'presentations')->get()->getRowArray();

        $roles = $db->table('core_roles r')
            ->select('r.id, r.name, r.description')
            ->join('core_role_modules rm', 'rm.role_id = r.id AND rm.module_id = ' . (int) ($mod['id'] ?? 0))
            ->orderBy('r.name')->get()->getResultArray();

        $granted = $perm
            ? array_column($db->table('core_role_permissions')->where('permission_id', $perm['id'])->get()->getResultArray(), 'role_id')
            : [];

        return view('App\Modules\Presentations\Views\presentations\permissions', [
            'pageTitle' => 'Permisos de Presentaciones',
            'roles'     => $roles,
            'granted'   => array_map('intval', $granted),
        ]);
    }

    public function save(): RedirectResponse
    {
        $db   = \Config\Database::connect();
        $perm = $db->table('core_permissions')->where('key', 'presentations.upload')->get()->getRowArray();
        if (! $perm) {
            return redirect()->back()->with('error', 'El permiso no existe. Ejecuta el seeder del módulo.');
        }

        $mod   = $db->table('core_modules')->where('key', 'presentations')->get()->getRowArray();
        $valid = array_column(
            $db->table('core_role_modules')->where('module_id', $mod['id'])->get()->getResultArray(),
            'role_id'
        );
        $chosen = array_values(array_intersect(array_map('intval', (array) $this->request->getPost('roles')), array_map('intval', $valid)));

        $db->transStart();
        $db->table('core_role_permissions')->where('permission_id', $perm['id'])->delete();
        foreach ($chosen as $roleId) {
            $db->table('core_role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $perm['id']]);
        }
        $db->transComplete();

        return redirect()->to(site_url('admin/presentations/permissions'))->with('success', 'Permisos actualizados.');
    }
}
