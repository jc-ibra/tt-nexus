<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;
use App\Modules\MailDispatch\Models\AttachmentModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Stores and validates MailDispatch attachments. Files live under
 * WRITEPATH/maildispatch/attachments/{message_id}/; the DB row (via
 * AttachmentModel) holds the relative path and metadata. Inbound attachments are
 * saved during ingestion; outbound ones are saved after a reply is sent.
 *
 * File names are sanitized and never trusted for filesystem paths (a numeric
 * prefix guarantees uniqueness), and blocked extensions are always download-only.
 */
class AttachmentService
{
    private const BASE_DIR = 'maildispatch/attachments';

    public function __construct(
        private AttachmentModel $model,
        private MailDispatchConfig $config
    ) {}

    // -----------------------------------------------------------------------
    // Ingestion (inbound)
    // -----------------------------------------------------------------------

    /**
     * Persists the normalized attachments of a freshly-ingested inbound message.
     * Each item: ['name','content_type','size','content'(raw bytes|null),
     * 'content_id','is_inline'].
     */
    public function storeIncoming(int $messageId, int $convId, array $attachments): void
    {
        $dir = $this->messageDir($messageId);
        $i   = 0;

        foreach ($attachments as $a) {
            $i++;
            $name    = $this->sanitizeName((string) ($a['name'] ?? '') ?: ('adjunto-' . $i));
            $content = $a['content'] ?? null;
            $size    = (int) ($a['size'] ?? (is_string($content) ? strlen($content) : 0));

            $relPath = '';
            $cap     = $this->config->maxIngestAttachmentBytes;
            if (is_string($content) && $content !== '' && ($cap === 0 || $size <= $cap)) {
                if (! is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $physical = $i . '_' . $name;
                if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $physical, $content) !== false) {
                    $relPath = self::BASE_DIR . '/' . $messageId . '/' . $physical;
                }
            }

            $this->model->insert([
                'message_id'      => $messageId,
                'conversation_id' => $convId,
                'filename'        => $name,
                'mime_type'       => (string) ($a['content_type'] ?? '') ?: null,
                'size_bytes'      => $size,
                'storage_path'    => $relPath,
                'content_id'      => (string) ($a['content_id'] ?? '') ?: null,
                'is_inline'       => ! empty($a['is_inline']) ? 1 : 0,
                'direction'       => 'in',
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Reply (outbound)
    // -----------------------------------------------------------------------

    /**
     * Validates the files posted with a reply. Returns ServiceResult::ok with the
     * usable UploadedFile[] as data, or ::fail with a user-facing message.
     */
    public function validateUploads(array $files): ServiceResult
    {
        $valid = [];
        $total = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            // Skip empty file inputs (no file chosen).
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (! $file->isValid()) {
                return ServiceResult::fail('Uno de los archivos no se subió correctamente: ' . $file->getErrorString());
            }
            $ext = strtolower($file->getClientExtension());
            if (in_array($ext, $this->config->blockedExtensions, true)) {
                return ServiceResult::fail('El tipo de archivo «.' . $ext . '» no está permitido.');
            }
            $total += (int) $file->getSize();
            $valid[] = $file;
        }

        if (count($valid) > $this->config->maxReplyAttachments) {
            return ServiceResult::fail('Máximo ' . $this->config->maxReplyAttachments . ' archivos por respuesta.');
        }
        if ($total > $this->config->maxTotalReplyBytes) {
            return ServiceResult::fail('Los adjuntos superan el límite de ' . $this->humanSize($this->config->maxTotalReplyBytes) . '.');
        }

        return ServiceResult::ok($valid);
    }

    /**
     * Copies the sent files into permanent storage and records them against the
     * outbound message. Called after the reply is actually sent. Reads from each
     * upload's temp path (still present until the request ends).
     *
     * @param UploadedFile[] $files
     */
    public function storeOutgoing(int $messageId, int $convId, array $files): void
    {
        $dir = $this->messageDir($messageId);
        $i   = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $i++;
            $name = $this->sanitizeName($file->getClientName() ?: ('adjunto-' . $i));
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $physical = $i . '_' . $name;
            $relPath  = '';
            if (@copy($file->getTempName(), $dir . DIRECTORY_SEPARATOR . $physical)) {
                $relPath = self::BASE_DIR . '/' . $messageId . '/' . $physical;
            }

            $this->model->insert([
                'message_id'      => $messageId,
                'conversation_id' => $convId,
                'filename'        => $name,
                'mime_type'       => $file->getClientMimeType() ?: null,
                'size_bytes'      => (int) $file->getSize(),
                'storage_path'    => $relPath,
                'content_id'      => null,
                'is_inline'       => 0,
                'direction'       => 'out',
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Retrieval / serving
    // -----------------------------------------------------------------------

    /** Absolute filesystem path for an attachment row, or null if unavailable. */
    public function absolutePath(array $row): ?string
    {
        $rel = (string) ($row['storage_path'] ?? '');
        if ($rel === '') {
            return null;
        }
        $abs  = realpath(WRITEPATH . $rel);
        $base = realpath(WRITEPATH . self::BASE_DIR);
        // Guard against path traversal: the resolved path must live under BASE_DIR.
        if ($abs === false || $base === false || strncmp($abs, $base, strlen($base)) !== 0) {
            return null;
        }
        return $abs;
    }

    /** 1x1 transparent GIF: swapped in for a cid: reference that goes unresolved (over the embed cap, or missing on disk), so the sandboxed iframe never shows a broken-image icon it cannot recover from (no allow-scripts). */
    private const BLANK_PIXEL = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    /**
     * Prepares a message's HTML body for display: rewrites cid: references to
     * the authenticated attachment URL only for the attachments actually
     * embedded in the body, up to $maxEmbedded. Everything else — attachments
     * past the cap, or without a matching cid: — comes back in `files`, to be
     * shown as a downloadable chip instead of fetched.
     *
     * This is what keeps a long thread from firing one request per inline
     * image per message on every render: the caller decides how many messages
     * get their body rendered at all (collapsed messages get none), and this
     * caps how many images even a single rendered message can pull.
     *
     * @param array<int,array<string,mixed>> $attachments
     * @return array{body:string, files:array<int,array<string,mixed>>}
     */
    public function prepareBody(string $html, array $attachments, bool $isHtml, int $maxEmbedded = 20): array
    {
        if (! $isHtml || trim($html) === '') {
            return ['body' => $html, 'files' => $attachments];
        }

        $files    = [];
        $embedded = 0;

        foreach ($attachments as $a) {
            $cid = (string) ($a['content_id'] ?? '');
            if ($cid === '' || empty($a['storage_path']) || stripos($html, 'cid:' . $cid) === false) {
                $files[] = $a;
                continue;
            }

            if ($embedded >= $maxEmbedded) {
                // Referenced, but the cap is already spent for this message:
                // stays out of the network entirely instead of being fetched.
                $html    = str_ireplace(['cid:<' . $cid . '>', 'cid:' . $cid], self::BLANK_PIXEL, $html);
                $files[] = $a;
                continue;
            }

            $html = str_ireplace(
                ['cid:<' . $cid . '>', 'cid:' . $cid],
                base_url('dispatch/attachments/' . (int) $a['id']),
                $html
            );
            $embedded++;
        }

        return ['body' => $this->addLazyImgAttrs($html), 'files' => $files];
    }

    /** Adds loading="lazy" decoding="async" to <img> tags that don't already carry them. */
    private function addLazyImgAttrs(string $html): string
    {
        $result = preg_replace_callback('/<img\b([^>]*)>/i', static function (array $m): string {
            $attrs = $m[1];
            if (! preg_match('/\bloading\s*=/i', $attrs)) {
                $attrs .= ' loading="lazy"';
            }
            if (! preg_match('/\bdecoding\s*=/i', $attrs)) {
                $attrs .= ' decoding="async"';
            }
            return '<img' . $attrs . '>';
        }, $html);

        return $result ?? $html;
    }

    /**
     * Cache validators for a stored attachment file. Attachments never change
     * once written (only `created_at` is tracked — see the model docblock), so
     * id + size + mtime is a cheap, stable ETag: no need to hash the bytes.
     *
     * @return array{etag:string, mtime:int, size:int}
     */
    public function validatorsFor(string $path, int $id): array
    {
        $mtime = @filemtime($path) ?: time();
        $size  = @filesize($path) ?: 0;

        return [
            'etag'  => '"' . $id . '-' . $size . '-' . $mtime . '"',
            'mtime' => $mtime,
            'size'  => $size,
        ];
    }

    /**
     * Whether the request's conditional headers already match these
     * validators — if so, the caller answers 304 without touching the file.
     */
    public function isFresh(IncomingRequest $request, string $etag, int $mtime): bool
    {
        $ifNoneMatch = trim($request->getHeaderLine('If-None-Match'));
        if ($ifNoneMatch !== '') {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (trim($candidate) === $etag) {
                    return true;
                }
            }
            return false;
        }

        $ifModifiedSince = trim($request->getHeaderLine('If-Modified-Since'));
        if ($ifModifiedSince !== '') {
            $since = strtotime($ifModifiedSince);
            if ($since !== false && $mtime <= $since) {
                return true;
            }
        }

        return false;
    }

    /** Streams a file straight to output without loading it fully into memory. */
    public function stream(string $path): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
    }

    /**
     * Reads a stored message's attachments back as raw buffers so they can be
     * re-attached to an outgoing mail (the "reenviar" action). Inline parts are
     * included and flagged, because the body that travels with them still
     * references their cid: — dropping them would forward a mail with broken
     * images (a signature logo, typically).
     *
     * Rows whose file is gone from disk are skipped rather than failing the
     * send: an old thread may have been pruned, and the text is still worth
     * forwarding. Names are made unique so each inline part can be addressed by
     * name when its Content-ID is rewritten.
     *
     * @return ServiceResult data = array<int,array{name:string,mime:string,content:string,inline:bool,content_id:string}>
     */
    public function readForResend(int $messageId): ServiceResult
    {
        $files = [];
        $used  = [];
        $total = 0;

        foreach ($this->model->forMessage($messageId) as $row) {
            $path = $this->absolutePath($row);
            if ($path === null || ! is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $name = $this->sanitizeName((string) ($row['filename'] ?? 'adjunto'));
            if (isset($used[$name])) {
                $used[$name]++;
                $ext  = pathinfo($name, PATHINFO_EXTENSION);
                $stem = $ext !== '' ? substr($name, 0, -(strlen($ext) + 1)) : $name;
                $name = $stem . '-' . $used[$name] . ($ext !== '' ? '.' . $ext : '');
            } else {
                $used[$name] = 1;
            }

            $total  += strlen($content);
            $files[] = [
                'name'       => $name,
                'mime'       => (string) ($row['mime_type'] ?? '') ?: 'application/octet-stream',
                'content'    => $content,
                'inline'     => (int) ($row['is_inline'] ?? 0) === 1,
                'content_id' => (string) ($row['content_id'] ?? ''),
            ];
        }

        if ($total > $this->config->maxTotalReplyBytes) {
            return ServiceResult::fail(
                'Los adjuntos del mensaje suman ' . $this->humanSize($total)
                . ' y superan el límite de ' . $this->humanSize($this->config->maxTotalReplyBytes)
                . ' por correo. Descárgalos y envíalos por separado.'
            );
        }

        return ServiceResult::ok($files);
    }

    /** Whether this MIME type may be shown inline in the browser. */
    public function isInlineSafe(?string $mime): bool
    {
        return $mime !== null && in_array(strtolower($mime), $this->config->inlineSafeMimes, true);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function messageDir(int $messageId): string
    {
        return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . self::BASE_DIR . DIRECTORY_SEPARATOR . $messageId;
    }

    /** Strips path components and unsafe characters; caps length. */
    private function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = preg_replace('/[\/:*?"<>|]+/', '_', $name) ?? $name;
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'adjunto';
        }
        if (strlen($name) > 180) {
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $name = substr($name, 0, 170) . ($ext !== '' ? '.' . $ext : '');
        }
        return $name;
    }

    public function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }
}
