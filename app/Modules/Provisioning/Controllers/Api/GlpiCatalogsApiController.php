<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\Provisioning\Services\GlpiCatalogService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * JSON API mirror of the GLPI additional-fields catalog manager.
 */
class GlpiCatalogsApiController extends BaseApiController
{
    private function svc(): GlpiCatalogService
    {
        return service('glpiCatalogService');
    }

    public function listCatalogs(): ResponseInterface
    {
        $svc = $this->svc();
        if (! $svc->isConfigured()) {
            return $this->error('La conexión a GLPI no está configurada.', 409);
        }
        try {
            return $this->success($svc->listCatalogs());
        } catch (\Throwable $e) {
            return $this->error('No se pudo conectar a GLPI: ' . $e->getMessage(), 502);
        }
    }

    public function listValues(string $slug): ResponseInterface
    {
        $table = $this->resolve($slug);
        if ($table === null) {
            return $this->notFound('Catálogo no encontrado.');
        }
        try {
            return $this->success($this->svc()->listValues($table));
        } catch (\Throwable $e) {
            return $this->error('No se pudo leer el catálogo: ' . $e->getMessage(), 502);
        }
    }

    public function createValue(string $slug): ResponseInterface
    {
        $table = $this->resolve($slug);
        if ($table === null) {
            return $this->notFound('Catálogo no encontrado.');
        }
        $in     = $this->request->getJSON(true) ?? $this->request->getPost();
        $result = $this->svc()->createValue($table, $in ?? []);

        return $result->success
            ? $this->successCreated($result->data)
            : $this->error($result->message);
    }

    public function updateValue(string $slug, int $id): ResponseInterface
    {
        $table = $this->resolve($slug);
        if ($table === null) {
            return $this->notFound('Catálogo no encontrado.');
        }
        $in     = $this->request->getJSON(true) ?? [];
        $result = $this->svc()->updateValue($table, $id, $in);

        return $result->success
            ? $this->success(null)
            : $this->error($result->message);
    }

    public function deleteValue(string $slug, int $id): ResponseInterface
    {
        $table = $this->resolve($slug);
        if ($table === null) {
            return $this->notFound('Catálogo no encontrado.');
        }
        $result = $this->svc()->deleteValue($table, $id);

        return $result->success
            ? $this->success(null)
            : $this->error($result->message);
    }

    /**
     * Bulk import. Body: { "values": [ { "name": "...", "comment": "..." }, ... ] }
     * or a plain array of names: { "values": ["A", "B"] }. All go to root level.
     */
    public function importValues(string $slug): ResponseInterface
    {
        $table = $this->resolve($slug);
        if ($table === null) {
            return $this->notFound('Catálogo no encontrado.');
        }

        $in     = $this->request->getJSON(true) ?? [];
        $values = $in['values'] ?? [];
        if (! is_array($values) || $values === []) {
            return $this->validationError(['values' => 'Se requiere un arreglo "values" con al menos un elemento.']);
        }

        $rows = array_map(
            static fn($v) => is_array($v)
                ? ['name' => (string) ($v['name'] ?? ''), 'comment' => (string) ($v['comment'] ?? '')]
                : ['name' => (string) $v, 'comment' => ''],
            $values,
        );

        $result = $this->svc()->importValues($table, $rows);

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    /** Nexus-only presentation prefs (classification, label, hidden) for all catalogs. */
    public function listPrefs(): ResponseInterface
    {
        $svc = $this->svc();
        if (! $svc->isConfigured()) {
            return $this->error('La conexión a GLPI no está configurada.', 409);
        }
        try {
            return $this->success($svc->listForManagement());
        } catch (\Throwable $e) {
            return $this->error('No se pudo leer los catálogos: ' . $e->getMessage(), 502);
        }
    }

    /**
     * Body: { "prefs": [ { "slug": "...", "label": "", "classification": "IDS", "hidden": false }, ... ] }
     */
    public function savePrefs(): ResponseInterface
    {
        $svc = $this->svc();
        if (! $svc->isConfigured()) {
            return $this->error('La conexión a GLPI no está configurada.', 409);
        }

        $in    = $this->request->getJSON(true) ?? [];
        $prefs = $in['prefs'] ?? [];
        if (! is_array($prefs) || $prefs === []) {
            return $this->validationError(['prefs' => 'Se requiere un arreglo "prefs".']);
        }

        $saved = 0;
        foreach ($prefs as $p) {
            $slug  = (string) ($p['slug'] ?? '');
            $table = $svc->resolveTable($slug);
            if ($table === null) {
                continue;
            }
            $result = $svc->savePref(
                $table,
                trim((string) ($p['label'] ?? '')),
                trim((string) ($p['classification'] ?? '')),
                (bool) ($p['hidden'] ?? false),
            );
            if ($result->success) {
                $saved++;
            }
        }

        return $this->success(['saved' => $saved]);
    }

    public function testConnection(): ResponseInterface
    {
        $result = service('glpiDbConnection')->test();
        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message, 502);
    }

    private function resolve(string $slug): ?string
    {
        if (! $this->svc()->isConfigured()) {
            return null;
        }
        try {
            return $this->svc()->resolveTable($slug);
        } catch (\Throwable) {
            return null;
        }
    }
}
