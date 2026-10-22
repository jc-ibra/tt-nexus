<?php

declare(strict_types=1);

namespace App\Modules\Shares\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\Shares\Services\ShareService;
use CodeIgniter\HTTP\ResponseInterface;

class SharesApiController extends BaseApiController
{
    private function svc(): ShareService
    {
        return new ShareService();
    }

    private function uid(): int
    {
        return (int) session()->get('user_id');
    }

    /** Body fields from JSON, form-data or a raw PUT, whichever the client sent. */
    private function input(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        return $this->request->getPost() ?: (array) $this->request->getRawInput();
    }

    private function presentFile(array $f): array
    {
        return [
            'id'            => (int) $f['id'],
            'title'         => $f['title'],
            'original_name' => $f['original_name'],
            'size_bytes'    => (int) $f['size_bytes'],
            'sha256'        => $f['sha256'],
            'owner'         => $f['owner_name'] ?? null,
            'link_count'    => isset($f['link_count']) ? (int) $f['link_count'] : null,
            'created_at'    => $f['created_at'],
            'updated_at'    => $f['updated_at'],
        ];
    }

    private function presentLink(array $l): array
    {
        return [
            'id'             => (int) $l['id'],
            'file_id'        => (int) $l['file_id'],
            'file_title'     => $l['file_title'] ?? null,
            'url'            => $this->svc()->publicUrl($l),
            'label'          => $l['label'],
            'status'         => $l['status'] ?? $this->svc()->status($l),
            'is_active'      => (bool) $l['is_active'],
            'expires_at'     => $l['expires_at'],
            'max_views'      => $l['max_views'] !== null ? (int) $l['max_views'] : null,
            'view_count'     => (int) $l['view_count'],
            'has_password'   => ! empty($l['password_hash']),
            'last_viewed_at' => $l['last_viewed_at'],
            'created_at'     => $l['created_at'],
        ];
    }

    public function files(): ResponseInterface
    {
        return $this->success(array_map([$this, 'presentFile'], $this->svc()->listFiles($this->uid())));
    }

    public function showFile(int $id): ResponseInterface
    {
        $f = $this->svc()->ownedFile($id, $this->uid());

        return $f === null ? $this->notFound('Archivo no encontrado') : $this->success($this->presentFile($f));
    }

    public function createFile(): ResponseInterface
    {
        $r = $this->svc()->createFile($this->request->getFile('file'), $this->uid(), $this->request->getPost('title'));
        if (! $r['success']) {
            return $this->error($r['message'], 422);
        }

        return $this->successCreated([
            'file' => $this->presentFile($r['file']),
            'link' => $r['link'] ? $this->presentLink($r['link']) : null,
        ]);
    }

    public function replaceFile(int $id): ResponseInterface
    {
        $f = $this->svc()->ownedFile($id, $this->uid());
        if ($f === null) {
            return $this->notFound('Archivo no encontrado');
        }
        $r = $this->svc()->replaceFile($f, $this->request->getFile('file'));

        return $r['success'] ? $this->success($this->presentFile($this->svc()->ownedFile($id, $this->uid()))) : $this->error($r['message'], 422);
    }

    public function updateFile(int $id): ResponseInterface
    {
        $f = $this->svc()->ownedFile($id, $this->uid());
        if ($f === null) {
            return $this->notFound('Archivo no encontrado');
        }
        $r = $this->svc()->renameFile($f, (string) ($this->input()['title'] ?? ''));

        return $r['success'] ? $this->success($this->presentFile($this->svc()->ownedFile($id, $this->uid()))) : $this->error($r['message'], 422);
    }

    public function deleteFile(int $id): ResponseInterface
    {
        $f = $this->svc()->ownedFile($id, $this->uid());
        if ($f === null) {
            return $this->notFound('Archivo no encontrado');
        }
        $this->svc()->deleteFile($f);

        return $this->success(null);
    }

    public function links(): ResponseInterface
    {
        return $this->success(array_map([$this, 'presentLink'], $this->svc()->listLinks($this->uid())));
    }

    public function createLink(int $fileId): ResponseInterface
    {
        if ($this->svc()->ownedFile($fileId, $this->uid()) === null) {
            return $this->notFound('Archivo no encontrado');
        }
        $r = $this->svc()->createLink($fileId, $this->uid(), $this->input());

        return $r['success'] ? $this->successCreated($this->presentLink($r['link'])) : $this->error($r['message'], 422);
    }

    public function updateLink(int $id): ResponseInterface
    {
        $l = $this->svc()->ownedLink($id, $this->uid());
        if ($l === null) {
            return $this->notFound('Enlace no encontrado');
        }
        $r = $this->svc()->updateLink($l, $this->input());

        return $r['success'] ? $this->success($this->presentLink($this->svc()->ownedLink($id, $this->uid()))) : $this->error($r['message'], 422);
    }

    public function revoke(int $id): ResponseInterface
    {
        return $this->toggle($id, false);
    }

    public function activate(int $id): ResponseInterface
    {
        return $this->toggle($id, true);
    }

    public function regenerate(int $id): ResponseInterface
    {
        $l = $this->svc()->ownedLink($id, $this->uid());
        if ($l === null) {
            return $this->notFound('Enlace no encontrado');
        }
        $this->svc()->regenerate($l);

        return $this->success($this->presentLink($this->svc()->ownedLink($id, $this->uid())));
    }

    public function deleteLink(int $id): ResponseInterface
    {
        $l = $this->svc()->ownedLink($id, $this->uid());
        if ($l === null) {
            return $this->notFound('Enlace no encontrado');
        }
        $this->svc()->deleteLink($l);

        return $this->success(null);
    }

    private function toggle(int $id, bool $active): ResponseInterface
    {
        $l = $this->svc()->ownedLink($id, $this->uid());
        if ($l === null) {
            return $this->notFound('Enlace no encontrado');
        }
        $this->svc()->setActive($l, $active);

        return $this->success($this->presentLink($this->svc()->ownedLink($id, $this->uid())));
    }
}
