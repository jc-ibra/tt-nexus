<?php

declare(strict_types=1);

namespace App\Modules\Shares\Controllers;

use App\Controllers\BaseController;
use App\Modules\Shares\Services\ShareService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class Shares extends BaseController
{
    private function svc(): ShareService
    {
        return new ShareService();
    }

    private function uid(): int
    {
        return (int) session()->get('user_id');
    }

    /** JSON reply used by every AJAX action of the screen. */
    private function reply(array $result, array $extra = []): ResponseInterface
    {
        $ok = (bool) ($result['success'] ?? false);

        return $this->response->setStatusCode($ok ? 200 : 422)->setJSON([
            'success' => $ok,
            'message' => $result['message'] ?? '',
        ] + $extra);
    }

    private function notFoundJson(): ResponseInterface
    {
        return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'No encontrado.']);
    }

    public function index(): string
    {
        $svc = $this->svc();

        return view('App\Modules\Shares\Views\shares\index', [
            'pageTitle'    => 'Enlaces públicos',
            'files'        => $svc->listFiles($this->uid()),
            'links'        => $svc->listLinks($this->uid()),
            'showOwner'    => service('access')->isSuperAdmin(),
            'publicBase'   => site_url('s') . '/',
        ]);
    }

    public function upload(): ResponseInterface
    {
        $result = $this->svc()->createFile(
            $this->request->getFile('file'),
            $this->uid(),
            $this->request->getPost('title')
        );

        return $this->reply($result);
    }

    public function replace(int $id): ResponseInterface
    {
        $file = $this->svc()->ownedFile($id, $this->uid());
        if ($file === null) {
            return $this->notFoundJson();
        }

        return $this->reply($this->svc()->replaceFile($file, $this->request->getFile('file')));
    }

    public function updateFile(int $id): ResponseInterface
    {
        $file = $this->svc()->ownedFile($id, $this->uid());
        if ($file === null) {
            return $this->notFoundJson();
        }

        return $this->reply($this->svc()->renameFile($file, (string) $this->request->getPost('title')));
    }

    public function deleteFile(int $id): ResponseInterface
    {
        $file = $this->svc()->ownedFile($id, $this->uid());
        if ($file === null) {
            return $this->notFoundJson();
        }
        $this->svc()->deleteFile($file);

        return $this->reply(['success' => true, 'message' => 'Archivo y enlaces eliminados.']);
    }

    public function preview(int $id): ResponseInterface
    {
        $file = $this->svc()->ownedFile($id, $this->uid());
        if ($file === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->svc()->stream($file, false);
    }

    public function createLink(int $fileId): ResponseInterface
    {
        $file = $this->svc()->ownedFile($fileId, $this->uid());
        if ($file === null) {
            return $this->notFoundJson();
        }
        $result = $this->svc()->createLink($fileId, $this->uid(), $this->request->getPost());

        return $this->reply($result, ['url' => isset($result['link']) ? $this->svc()->publicUrl($result['link']) : null]);
    }

    public function updateLink(int $id): ResponseInterface
    {
        $link = $this->svc()->ownedLink($id, $this->uid());
        if ($link === null) {
            return $this->notFoundJson();
        }

        return $this->reply($this->svc()->updateLink($link, $this->request->getPost()));
    }

    public function revoke(int $id): ResponseInterface
    {
        return $this->toggle($id, false, 'Enlace desactivado. Ya no abre.');
    }

    public function activate(int $id): ResponseInterface
    {
        return $this->toggle($id, true, 'Enlace reactivado.');
    }

    public function regenerate(int $id): ResponseInterface
    {
        $link = $this->svc()->ownedLink($id, $this->uid());
        if ($link === null) {
            return $this->notFoundJson();
        }
        $token = $this->svc()->regenerate($link);

        return $this->reply(
            ['success' => true, 'message' => 'URL regenerada. La anterior dejó de funcionar.'],
            ['url' => site_url('s/' . $token)]
        );
    }

    public function deleteLink(int $id): ResponseInterface
    {
        $link = $this->svc()->ownedLink($id, $this->uid());
        if ($link === null) {
            return $this->notFoundJson();
        }
        $this->svc()->deleteLink($link);

        return $this->reply(['success' => true, 'message' => 'Enlace eliminado.']);
    }

    private function toggle(int $id, bool $active, string $message): ResponseInterface
    {
        $link = $this->svc()->ownedLink($id, $this->uid());
        if ($link === null) {
            return $this->notFoundJson();
        }
        $this->svc()->setActive($link, $active);

        return $this->reply(['success' => true, 'message' => $message]);
    }
}
