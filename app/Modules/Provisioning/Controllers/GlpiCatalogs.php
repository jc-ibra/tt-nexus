<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Controllers\BaseController;
use App\Modules\Provisioning\Services\GlpiCatalogService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Manages the values of GLPI additional-fields dropdown catalogs from Nexus.
 * All GLPI DB access goes through GlpiCatalogService, which validates every
 * table name against the live discovery whitelist.
 */
class GlpiCatalogs extends BaseController
{
    private function svc(): GlpiCatalogService
    {
        return service('glpiCatalogService');
    }

    /** URL of the GLPI provisioning system detail page (the hub for these tools). */
    private function systemUrl(): string
    {
        $glpi = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->findByKey('glpi');
        return $glpi
            ? route_to('provisioning.systems.show', $glpi['id'])
            : route_to('provisioning.systems.index');
    }

    public function index(): string
    {
        $svc        = $this->svc();
        $configured = $svc->isConfigured();
        $catalogs   = [];
        $error      = null;

        if ($configured) {
            try {
                $catalogs = $svc->listCatalogs();
            } catch (\Throwable $e) {
                $error = 'No se pudo conectar a la base de datos de GLPI: ' . $e->getMessage();
            }
        }

        return view('App\Modules\Provisioning\Views\glpi_catalogs\index', [
            'pageTitle'  => 'Catálogos GLPI',
            'configured' => $configured,
            'catalogs'   => $catalogs,
            'error'      => $error,
            'systemUrl'  => $this->systemUrl(),
        ]);
    }

    /**
     * Nexus-only management of catalog presentation: classification (group),
     * label and visibility. Does NOT touch GLPI.
     */
    public function manage(): string|ResponseInterface
    {
        $svc = $this->svc();
        if (! $svc->isConfigured()) {
            session()->setFlashdata('error', 'Configura primero la conexión a GLPI.');
            return redirect()->to($this->systemUrl());
        }

        try {
            $data = $svc->listForManagement();
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'No se pudo leer los catálogos: ' . $e->getMessage());
            return redirect()->to($this->systemUrl());
        }

        return view('App\Modules\Provisioning\Views\glpi_catalogs\manage', [
            'pageTitle'       => 'Clasificaciones y visibilidad',
            'rows'            => $data['rows'],
            'classifications' => $data['classifications'],
            'systemUrl'       => $this->systemUrl(),
        ]);
    }

    public function saveManage(): ResponseInterface
    {
        $svc     = $this->svc();
        $classes = $this->request->getPost('classification') ?? [];
        $labels  = $this->request->getPost('label') ?? [];
        $hidden  = $this->request->getPost('hidden') ?? [];

        if (! is_array($classes)) {
            $classes = [];
        }

        $saved = 0;
        foreach ($classes as $slug => $class) {
            $slug  = (string) $slug;
            $table = $svc->resolveTable($slug);
            if ($table === null) {
                continue;
            }
            $result = $svc->savePref(
                $table,
                trim((string) ($labels[$slug] ?? '')),
                trim((string) $class),
                isset($hidden[$slug]),
            );
            if ($result->success) {
                $saved++;
            }
        }

        session()->setFlashdata('success', "Clasificaciones y visibilidad actualizadas ({$saved} catálogos).");
        return redirect()->to(route_to('provisioning.glpi-catalogs.manage'));
    }

    public function show(string $slug): string|ResponseInterface
    {
        $svc = $this->svc();
        if (! $svc->isConfigured()) {
            session()->setFlashdata('error', 'Configura primero la conexión a GLPI.');
            return redirect()->to(route_to('provisioning.glpi-catalogs.index'));
        }

        $table = $svc->resolveTable($slug);
        if ($table === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $values  = $svc->listValues($table);
            $parents = $svc->parentOptions($table);
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Error al leer el catálogo: ' . $e->getMessage());
            return redirect()->to(route_to('provisioning.glpi-catalogs.index'));
        }

        return view('App\Modules\Provisioning\Views\glpi_catalogs\show', [
            'pageTitle' => 'Catálogo: ' . $svc->labelFor($table),
            'slug'      => $slug,
            'label'     => $svc->labelFor($table),
            'values'    => $values,
            'parents'   => $parents,
        ]);
    }

    public function import(string $slug): ResponseInterface
    {
        $svc   = $this->svc();
        $table = $svc->resolveTable($slug);
        if ($table === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $showUrl = route_to('provisioning.glpi-catalogs.show', $slug);
        $file    = $this->request->getFile('csv');

        if ($file === null || ! $file->isValid()) {
            session()->setFlashdata('error', 'Selecciona un archivo CSV válido.');
            return redirect()->to($showUrl);
        }

        $rows = $this->parseCsv($file->getTempName());
        if ($rows === []) {
            session()->setFlashdata('error', 'El archivo no contiene valores.');
            return redirect()->to($showUrl);
        }

        $this->flash($svc->importValues($table, $rows));
        return redirect()->to($showUrl);
    }

    /**
     * Parses a simple CSV: first column = name, optional second = comment.
     * Auto-detects the delimiter and skips an optional header row.
     *
     * @return array<int,array{name:string,comment:string}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        // Sniff delimiter from the first non-empty line.
        $delimiter = ',';
        $peek      = fgets($handle);
        if ($peek !== false) {
            $counts = [',' => substr_count($peek, ','), ';' => substr_count($peek, ';'), "\t" => substr_count($peek, "\t")];
            $delimiter = array_search(max($counts), $counts, true) ?: ',';
        }
        rewind($handle);

        $rows  = [];
        $first = true;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $name = isset($data[0]) ? trim((string) $data[0]) : '';

            if ($first) {
                $first = false;
                if (in_array(mb_strtolower($name), ['name', 'nombre', 'valor', 'value'], true)) {
                    continue; // header row
                }
            }
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'name'    => $name,
                'comment' => isset($data[1]) ? trim((string) $data[1]) : '',
            ];
        }
        fclose($handle);

        return $rows;
    }

    public function store(string $slug): ResponseInterface
    {
        $svc   = $this->svc();
        $table = $svc->resolveTable($slug);
        if ($table === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $svc->createValue($table, $this->request->getPost());
        $this->flash($result);

        return redirect()->to(route_to('provisioning.glpi-catalogs.show', $slug))->withInput();
    }

    public function edit(string $slug, int $id): string|ResponseInterface
    {
        $svc   = $this->svc();
        $table = $svc->resolveTable($slug);
        if ($table === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $value = $svc->findValue($table, $id);
        if ($value === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Provisioning\Views\glpi_catalogs\form', [
            'pageTitle' => 'Editar valor: ' . $value['name'],
            'slug'      => $slug,
            'label'     => $svc->labelFor($table),
            'value'     => $value,
            'parents'   => $svc->parentOptions($table, $id),
        ]);
    }

    public function update(string $slug, int $id): ResponseInterface
    {
        $svc   = $this->svc();
        $table = $svc->resolveTable($slug);
        if ($table === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $svc->updateValue($table, $id, $this->request->getPost());
        if (! $result->success) {
            session()->setFlashdata('error', $result->message);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', $result->message);
        return redirect()->to(route_to('provisioning.glpi-catalogs.show', $slug));
    }

    public function destroy(string $slug, int $id): ResponseInterface
    {
        $svc   = $this->svc();
        $table = $svc->resolveTable($slug);
        if ($table === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->flash($svc->deleteValue($table, $id));

        return redirect()->to(route_to('provisioning.glpi-catalogs.show', $slug));
    }

    private function flash(\App\Modules\Core\Services\ServiceResult $result): void
    {
        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);
    }
}
