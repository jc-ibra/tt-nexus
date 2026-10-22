<?php

declare(strict_types=1);

namespace App\Modules\Shares\Services;

use App\Modules\Shares\Models\ShareFileModel;
use App\Modules\Shares\Models\ShareLinkModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class ShareService
{
    public const MAX_KB = 102400; // 100 MB

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_REVOKED   = 'revoked';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_EXHAUSTED = 'exhausted';

    public function __construct(
        private ?ShareFileModel $files = null,
        private ?ShareLinkModel $links = null,
    ) {
        $this->files ??= new ShareFileModel();
        $this->links ??= new ShareLinkModel();
    }

    // ------------------------------------------------------------------
    // Reads (ownership-scoped: non-SuperAdmins only see their own rows)
    // ------------------------------------------------------------------

    public function listFiles(int $userId): array
    {
        return $this->files->listFor($this->scope($userId));
    }

    public function listLinks(int $userId): array
    {
        $rows = $this->links->listFor($this->scope($userId));
        foreach ($rows as &$r) {
            $r['status'] = $this->status($r);
        }

        return $rows;
    }

    /** Returns null when the file does not exist or is not the user's. */
    public function ownedFile(int $id, int $userId): ?array
    {
        $f = $this->files->find($id);
        if ($f === null || ! $this->canManage((int) $f['owner_id'], $userId)) {
            return null;
        }

        return $f;
    }

    public function ownedLink(int $id, int $userId): ?array
    {
        $l = $this->links->findWithFile($id);
        if ($l === null || ! $this->canManage((int) $l['owner_id'], $userId)) {
            return null;
        }
        $l['status'] = $this->status($l);

        return $l;
    }

    public function path(array $file): string
    {
        return $this->dir() . $file['stored_name'];
    }

    public function publicUrl(array $link): string
    {
        return site_url('s/' . $link['token']);
    }

    // ------------------------------------------------------------------
    // Files
    // ------------------------------------------------------------------

    /**
     * Store a new HTML and create its first link.
     *
     * @return array{success: bool, message: string, file?: array, link?: array}
     */
    public function createFile(?UploadedFile $upload, int $userId, ?string $title): array
    {
        $check = $this->validateUpload($upload);
        if (! $check['success']) {
            return $check;
        }

        $stored = $this->moveUpload($upload);
        if ($stored === null) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor.'];
        }

        $now   = date('Y-m-d H:i:s');
        $name  = mb_substr(basename($upload->getClientName()), 0, 255);
        $title = trim((string) $title);
        $id    = (int) $this->files->insert([
            'owner_id'      => $userId,
            'title'         => mb_substr($title !== '' ? $title : pathinfo($name, PATHINFO_FILENAME), 0, 150),
            'original_name' => $name,
            'stored_name'   => $stored['stored_name'],
            'size_bytes'    => $stored['size'],
            'sha256'        => $check['sha'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $link = $this->createLink($id, $userId, []);

        return ['success' => true, 'message' => 'Archivo publicado.', 'file' => $this->files->find($id), 'link' => $link['link'] ?? null];
    }

    /** @return array{success: bool, message: string} */
    public function replaceFile(array $file, ?UploadedFile $upload): array
    {
        $check = $this->validateUpload($upload);
        if (! $check['success']) {
            return $check;
        }
        if ($check['sha'] === $file['sha256']) {
            return ['success' => false, 'message' => 'Es el mismo archivo que ya está publicado.'];
        }

        $stored = $this->moveUpload($upload);
        if ($stored === null) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor.'];
        }

        $old = $this->path($file);
        $this->files->update((int) $file['id'], [
            'original_name' => mb_substr(basename($upload->getClientName()), 0, 255),
            'stored_name'   => $stored['stored_name'],
            'size_bytes'    => $stored['size'],
            'sha256'        => $check['sha'],
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        if (is_file($old)) {
            @unlink($old);
        }

        return ['success' => true, 'message' => 'Archivo reemplazado. Los enlaces siguen siendo los mismos.'];
    }

    public function renameFile(array $file, string $title): array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 150) {
            return ['success' => false, 'message' => 'El título es obligatorio y admite hasta 150 caracteres.'];
        }
        $this->files->update((int) $file['id'], ['title' => $title, 'updated_at' => date('Y-m-d H:i:s')]);

        return ['success' => true, 'message' => 'Título actualizado.'];
    }

    public function deleteFile(array $file): void
    {
        $path = $this->path($file);
        $this->files->delete((int) $file['id']); // links cascade
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // ------------------------------------------------------------------
    // Links
    // ------------------------------------------------------------------

    /**
     * @param array{label?: ?string, expires_at?: ?string, max_views?: mixed, password?: ?string} $input
     * @return array{success: bool, message: string, link?: array, errors?: array}
     */
    public function createLink(int $fileId, int $userId, array $input): array
    {
        $parsed = $this->parseLinkInput($input);
        if ($parsed['errors']) {
            return ['success' => false, 'message' => implode(' ', $parsed['errors']), 'errors' => $parsed['errors']];
        }

        $now = date('Y-m-d H:i:s');
        $id  = (int) $this->links->insert($parsed['data'] + [
            'file_id'    => $fileId,
            'token'      => $this->newToken(),
            'is_active'  => 1,
            'view_count' => 0,
            'created_by' => $userId ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['success' => true, 'message' => 'Enlace creado.', 'link' => $this->links->findWithFile($id)];
    }

    /**
     * Edit label / expiry / view cap / password. Keys absent from $input are left untouched.
     *
     * @return array{success: bool, message: string, errors?: array}
     */
    public function updateLink(array $link, array $input): array
    {
        $parsed = $this->parseLinkInput($input, partial: true);
        if ($parsed['errors']) {
            return ['success' => false, 'message' => implode(' ', $parsed['errors']), 'errors' => $parsed['errors']];
        }

        $data = $parsed['data'];
        if (! empty($input['reset_views'])) {
            $data['view_count'] = 0;
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->links->update((int) $link['id'], $data);

        return ['success' => true, 'message' => 'Enlace actualizado.'];
    }

    public function setActive(array $link, bool $active): void
    {
        $this->links->update((int) $link['id'], ['is_active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** New token: the previous URL stops working immediately. */
    public function regenerate(array $link): string
    {
        $token = $this->newToken();
        $this->links->update((int) $link['id'], ['token' => $token, 'updated_at' => date('Y-m-d H:i:s')]);

        return $token;
    }

    public function deleteLink(array $link): void
    {
        $this->links->delete((int) $link['id']);
    }

    // ------------------------------------------------------------------
    // Public access
    // ------------------------------------------------------------------

    public function resolvePublic(string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }
        $link = $this->links->findByToken($token);
        if ($link === null) {
            return null;
        }
        $link['status'] = $this->status($link);

        return $link;
    }

    /**
     * Atomically count one view. Returns false when the link stopped being
     * valid between the status check and now (revoked, expired, cap reached).
     */
    public function registerView(int $linkId): bool
    {
        $now = date('Y-m-d H:i:s');
        \Config\Database::connect()->query(
            'UPDATE shares_links SET view_count = view_count + 1, last_viewed_at = ? '
            . 'WHERE id = ? AND is_active = 1 '
            . 'AND (max_views IS NULL OR view_count < max_views) '
            . 'AND (expires_at IS NULL OR expires_at > ?)',
            [$now, $linkId, $now]
        );

        return \Config\Database::connect()->affectedRows() > 0;
    }

    public function status(array $link): string
    {
        if (! (int) $link['is_active']) {
            return self::STATUS_REVOKED;
        }
        if (! empty($link['expires_at']) && strtotime($link['expires_at']) <= time()) {
            return self::STATUS_EXPIRED;
        }
        if ($link['max_views'] !== null && (int) $link['view_count'] >= (int) $link['max_views']) {
            return self::STATUS_EXHAUSTED;
        }

        return self::STATUS_ACTIVE;
    }

    /** Token stored in the session once the password is accepted; invalidated if the password changes. */
    public function unlockMarker(array $link): string
    {
        return hash('sha256', (string) $link['password_hash']);
    }

    /**
     * Stream the stored HTML in a sandboxed response and stop the request.
     * Public views never cache, so revocation and view counts apply on every load.
     */
    public function stream(array $file, bool $public): never
    {
        $path = $this->path($file);
        if (! is_file($path)) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');

        if ($public) {
            header('Cache-Control: no-store');
        } else {
            $etag = '"' . $file['sha256'] . '"';
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('ETag: ' . $etag);
            if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
                http_response_code(304);
                exit;
            }
        }

        // Opaque origin: the uploaded page's scripts cannot reach Nexus' session.
        header('Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals allow-forms');
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function scope(int $userId): ?int
    {
        return service('access')->isSuperAdmin() ? null : $userId;
    }

    private function canManage(int $ownerId, int $userId): bool
    {
        return $ownerId === $userId || service('access')->isSuperAdmin();
    }

    private function newToken(): string
    {
        do {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } while ($this->links->where('token', $token)->countAllResults() > 0);

        return $token;
    }

    /**
     * @return array{data: array, errors: list<string>}
     */
    private function parseLinkInput(array $in, bool $partial = false): array
    {
        $data   = [];
        $errors = [];

        if (array_key_exists('label', $in) || ! $partial) {
            $label = trim((string) ($in['label'] ?? ''));
            if (mb_strlen($label) > 150) {
                $errors[] = 'La etiqueta admite hasta 150 caracteres.';
            }
            $data['label'] = $label !== '' ? $label : null;
        }

        if (array_key_exists('expires_at', $in) || ! $partial) {
            $raw = trim((string) ($in['expires_at'] ?? ''));
            if ($raw === '') {
                $data['expires_at'] = null;
            } elseif (($ts = strtotime($raw)) === false) {
                $errors[] = 'La fecha de expiración no es válida.';
            } else {
                $data['expires_at'] = date('Y-m-d H:i:s', $ts);
            }
        }

        if (array_key_exists('max_views', $in) || ! $partial) {
            $raw = trim((string) ($in['max_views'] ?? ''));
            if ($raw === '') {
                $data['max_views'] = null;
            } elseif (! ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 1000000) {
                $errors[] = 'El límite de vistas debe ser un entero entre 1 y 1,000,000.';
            } else {
                $data['max_views'] = (int) $raw;
            }
        }

        if (! empty($in['clear_password'])) {
            $data['password_hash'] = null;
        } elseif (isset($in['password']) && trim((string) $in['password']) !== '') {
            $pw = (string) $in['password'];
            if (mb_strlen($pw) < 4 || mb_strlen($pw) > 72) {
                $errors[] = 'La contraseña debe tener entre 4 y 72 caracteres.';
            } else {
                $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /** @return array{success: bool, message: string, sha?: string} */
    private function validateUpload(?UploadedFile $file): array
    {
        if ($file === null || ! $file->isValid()) {
            $err = $file?->getErrorString() ?: 'No se recibió ningún archivo.';

            return ['success' => false, 'message' => $err . ' (límite del servidor: ' . ini_get('upload_max_filesize') . ')'];
        }
        if (! in_array(strtolower($file->getClientExtension()), ['html', 'htm'], true)) {
            return ['success' => false, 'message' => 'El archivo debe ser .html o .htm.'];
        }
        if ($file->getSize() > self::MAX_KB * 1024) {
            return ['success' => false, 'message' => 'El archivo supera el límite de 100 MB.'];
        }

        $head = (string) file_get_contents($file->getTempName(), false, null, 0, 2048);
        $head = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $head));
        if (! preg_match('/^(<!doctype\s+html|<html)/i', $head)) {
            return ['success' => false, 'message' => 'El archivo no parece un documento HTML.'];
        }

        return ['success' => true, 'message' => '', 'sha' => hash_file('sha256', $file->getTempName())];
    }

    /** @return array{stored_name: string, size: int}|null */
    private function moveUpload(UploadedFile $file): ?array
    {
        $dir = $this->dir();
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return null;
        }

        $stored = bin2hex(random_bytes(16)) . '.html';
        $size   = $file->getSize();
        $file->move($dir, $stored);

        return ['stored_name' => $stored, 'size' => $size];
    }

    private function dir(): string
    {
        return WRITEPATH . 'uploads/shares/';
    }
}
