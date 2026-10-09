<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\Presentations\Services\PresentationService;
use CodeIgniter\HTTP\ResponseInterface;

class PresentationsApiController extends BaseApiController
{
    private function svc(): PresentationService
    {
        return new PresentationService();
    }

    private function present(array $v): array
    {
        return [
            'id'            => (int) $v['id'],
            'original_name' => $v['original_name'],
            'size_bytes'    => (int) $v['size_bytes'],
            'sha256'        => $v['sha256'],
            'notes'         => $v['notes'],
            'uploaded_by'   => $v['uploader_name'] ?? null,
            'created_at'    => $v['created_at'],
        ];
    }

    public function index(): ResponseInterface
    {
        return $this->success(array_map([$this, 'present'], $this->svc()->list()));
    }

    public function current(): ResponseInterface
    {
        $v = $this->svc()->current();

        return $v === null ? $this->notFound('Aún no hay presentación publicada') : $this->success($this->present($v));
    }

    public function raw(int $id): ResponseInterface
    {
        $v = $this->svc()->find($id);
        if ($v === null) {
            return $this->notFound('Versión no encontrada');
        }

        $this->svc()->stream($v);
    }

    public function create(): ResponseInterface
    {
        if (! service('access')->can(PresentationService::PERMISSION_UPLOAD)) {
            return $this->error('No tienes permiso para reemplazar la presentación', 403);
        }

        $result = $this->svc()->store(
            $this->request->getFile('file'),
            (int) session()->get('user_id'),
            $this->request->getPost('notes')
        );

        return $result['success']
            ? $this->successCreated($this->present($result['version']))
            : $this->error($result['message'], 422);
    }
}
