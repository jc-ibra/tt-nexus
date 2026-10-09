<?php

declare(strict_types=1);

namespace App\Modules\Presentations\Services;

use App\Modules\Presentations\Models\PresentationVersionModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class PresentationService
{
    public const PERMISSION_UPLOAD = 'presentations.upload';
    public const MAX_KB            = 102400; // 100 MB

    public function __construct(private ?PresentationVersionModel $model = null)
    {
        $this->model ??= new PresentationVersionModel();
    }

    public function current(): ?array
    {
        return $this->model->latest();
    }

    public function list(): array
    {
        return $this->model->listWithUploader();
    }

    public function find(int $id): ?array
    {
        return $this->model->findWithUploader($id);
    }

    public function path(array $version): string
    {
        return $this->dir() . $version['stored_name'];
    }

    /**
     * Validate and store an uploaded HTML as a new version.
     *
     * @return array{success: bool, message: string, version?: array}
     */
    public function store(?UploadedFile $file, int $userId, ?string $notes): array
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

        $sha = hash_file('sha256', $file->getTempName());
        $cur = $this->model->latest();
        if ($cur !== null && $cur['sha256'] === $sha) {
            return ['success' => false, 'message' => 'Es el mismo archivo que la versión vigente.'];
        }

        $dir = $this->dir();
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio de almacenamiento.'];
        }

        $stored = bin2hex(random_bytes(16)) . '.html';
        $size   = $file->getSize();
        $name   = mb_substr(basename($file->getClientName()), 0, 255);
        $file->move($dir, $stored);

        $notes = $notes !== null ? mb_substr(trim($notes), 0, 500) : null;
        $id    = $this->model->insert([
            'original_name' => $name,
            'stored_name'   => $stored,
            'size_bytes'    => $size,
            'sha256'        => $sha,
            'notes'         => $notes !== '' ? $notes : null,
            'uploaded_by'   => $userId ?: null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Presentación publicada.', 'version' => $this->find((int) $id)];
    }

    /** Stream the stored HTML in a sandboxed response and stop the request. */
    public function stream(array $version): never
    {
        $path = $this->path($version);
        if (! is_file($path)) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }

        $etag = '"' . $version['sha256'] . '"';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('X-Robots-Tag: noindex');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        // Opaque origin: the uploaded page's scripts cannot reach Nexus' session.
        header('Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals allow-forms');
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function dir(): string
    {
        return WRITEPATH . 'uploads/presentations/';
    }
}
