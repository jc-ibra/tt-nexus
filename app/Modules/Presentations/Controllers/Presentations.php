<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Controllers;

use App\Controllers\BaseController;
use App\Modules\Presentations\Services\PresentationService;
use CodeIgniter\HTTP\ResponseInterface;

class Presentations extends BaseController
{
    private function svc(): PresentationService
    {
        return new PresentationService();
    }

    private function canUpload(): bool
    {
        return service('access')->can(PresentationService::PERMISSION_UPLOAD);
    }

    public function index(): string
    {
        return $this->renderViewer($this->svc()->current());
    }

    public function show(int $id): string|ResponseInterface
    {
        $version = $this->svc()->find($id);
        if ($version === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->renderViewer($version);
    }

    public function history(): string
    {
        return view('App\Modules\Presentations\Views\presentations\history', [
            'pageTitle' => 'Historial de presentaciones',
            'versions'  => $this->svc()->list(),
            'canUpload' => $this->canUpload(),
        ]);
    }

    public function raw(int $id): ResponseInterface
    {
        $version = $this->svc()->find($id);
        if ($version === null) {
            return $this->response->setStatusCode(404)->setBody('No encontrado.');
        }

        $this->svc()->stream($version);
    }

    public function upload(): ResponseInterface
    {
        if (! $this->canUpload()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false, 'message' => 'No tienes permiso para reemplazar la presentación.',
            ]);
        }

        $result = $this->svc()->store(
            $this->request->getFile('file'),
            (int) session()->get('user_id'),
            $this->request->getPost('notes')
        );

        return $this->response->setStatusCode($result['success'] ? 200 : 422)->setJSON([
            'success' => $result['success'],
            'message' => $result['message'],
            'redirect' => $result['success'] ? site_url('presentations') : null,
        ]);
    }

    private function renderViewer(?array $version): string
    {
        return view('App\Modules\Presentations\Views\presentations\show', [
            'pageTitle' => 'Presentaciones',
            'version'   => $version,
            'isCurrent' => $version === null || (int) $version['id'] === (int) ($this->svc()->current()['id'] ?? 0),
            'canUpload' => $this->canUpload(),
        ]);
    }
}
