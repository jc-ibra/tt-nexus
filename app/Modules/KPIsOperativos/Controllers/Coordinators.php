<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Controllers;

use App\Controllers\BaseController;

/**
 * Catálogo editable de coordinadores/gerentes por zona regional.
 * Reemplaza al dict hardcodeado COORDINADORES del script Python original.
 */
class Coordinators extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $rows = $db->table('glpi_coordinators')->orderBy('zone', 'ASC')->get()->getResultArray();

        return view('App\Modules\KPIsOperativos\Views\coordinators\index', [
            'pageTitle'    => 'Coordinadores GLPI',
            'coordinators' => $rows,
        ]);
    }

    public function new(): string
    {
        return view('App\Modules\KPIsOperativos\Views\coordinators\form', [
            'pageTitle'   => 'Nueva zona',
            'coordinator' => null,
        ]);
    }

    public function store()
    {
        $data = $this->request->getPost(['zone', 'coord_name', 'gte_name', 'is_active']);

        if (empty($data['zone']) || empty($data['coord_name'])) {
            session()->setFlashdata('errors', ['Zona y coordinador son obligatorios.']);
            return redirect()->back()->withInput();
        }

        $db = db_connect();
        $exists = $db->table('glpi_coordinators')->where('zone', $data['zone'])->countAllResults();
        if ($exists > 0) {
            session()->setFlashdata('errors', ['Ya existe una zona con ese nombre.']);
            return redirect()->back()->withInput();
        }

        $now = date('Y-m-d H:i:s');
        $db->table('glpi_coordinators')->insert([
            'zone'       => trim((string) $data['zone']),
            'coord_name' => trim((string) $data['coord_name']),
            'gte_name'   => trim((string) ($data['gte_name'] ?? '')),
            'is_active'  => ! empty($data['is_active']) ? 1 : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        session()->setFlashdata('success', 'Zona creada.');
        return redirect()->to(route_to('kpi.coordinators.index'));
    }

    public function edit(int $id): string
    {
        $db  = db_connect();
        $row = $db->table('glpi_coordinators')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\KPIsOperativos\Views\coordinators\form', [
            'pageTitle'   => 'Editar zona',
            'coordinator' => $row,
        ]);
    }

    public function update(int $id)
    {
        $db  = db_connect();
        $row = $db->table('glpi_coordinators')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $data = $this->request->getPost(['zone', 'coord_name', 'gte_name', 'is_active']);

        if (empty($data['zone']) || empty($data['coord_name'])) {
            session()->setFlashdata('errors', ['Zona y coordinador son obligatorios.']);
            return redirect()->back()->withInput();
        }

        $db->table('glpi_coordinators')->where('id', $id)->update([
            'zone'       => trim((string) $data['zone']),
            'coord_name' => trim((string) $data['coord_name']),
            'gte_name'   => trim((string) ($data['gte_name'] ?? '')),
            'is_active'  => ! empty($data['is_active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('success', 'Zona actualizada.');
        return redirect()->to(route_to('kpi.coordinators.index'));
    }

    public function destroy(int $id)
    {
        $db  = db_connect();
        $row = $db->table('glpi_coordinators')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db->table('glpi_coordinators')->where('id', $id)->delete();
        session()->setFlashdata('success', "Zona «{$row['zone']}» eliminada.");
        return redirect()->to(route_to('kpi.coordinators.index'));
    }
}
