<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * IMAP read backend for MailDispatch, mirroring GraphMailService's role for the
 * plain-IMAP provider. Connects to a mailbox that receives every helpdesk
 * message via a server-side forwarding rule (both inbound customer mail and
 * copies of agent replies), and returns messages normalized to the exact same
 * flat shape ConversationService::extract() already consumes for Graph — so the
 * threading and state machine are reused unchanged.
 *
 * Uses webklex/php-imap (pure PHP; no ext-imap needed). Credentials are injected
 * from MailDispatchSettings; this service never reads the DB or .env directly.
 *
 * Incremental sync is UID based: the cursor "UIDVALIDITY:<v>;UID:<lastUid>" is
 * persisted in maildispatch_sync_state.delta_link. A changed UIDVALIDITY (folder
 * rebuilt on the server) resets the cursor to a full pull, since UIDs are no
 * longer comparable.
 *
 * Memory: bodies are fetched one message at a time and streamed to the caller.
 * webklex keeps the whole raw IMAP response of a FETCH in memory (plus the
 * parsed copy), so fetching a full page of bodies in a single command made peak
 * usage proportional to the total size of the page and exhausted PHP's limit on
 * mailboxes with attachments.
 */
class ImapMailService
{
    /**
     * Bodies above this size are not downloaded. The message is still ingested
     * (headers, subject, sender, date) with a placeholder body so it stays
     * visible in the queue, but its content and attachments are left on the
     * server: a single ~40 MB message costs several times its size while being
     * read, decoded and stored, and would exhaust memory on its own.
     */
    private const MAX_BODY_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,   // 'ssl' | 'tls' | 'none'
        private bool $validateCert,
        private string $username,
        private string $password,
        private string $folder,
        private string $mailbox       // logical helpdesk address (direction detection)
    ) {}

    public function mailbox(): string { return $this->mailbox; }
    public function folder(): string  { return $this->folder; }

    // -----------------------------------------------------------------------
    // Connectivity check (admin "Probar conexión")
    // -----------------------------------------------------------------------

    public function testConnection(): ServiceResult
    {
        if ($this->host === '' || $this->username === '') {
            return ServiceResult::fail('Configura host, usuario y contraseña IMAP antes de probar la conexión.');
        }

        try {
            $client = $this->connect();
            $folder = $client->getFolder($this->folder);
            if ($folder === null) {
                $client->disconnect();
                return ServiceResult::fail("Conexión establecida, pero no se encontró la carpeta «{$this->folder}».");
            }
            $status = $folder->examine();
            $client->disconnect();

            $exists = (int) ($status['exists'] ?? 0);
            return ServiceResult::ok(
                ['exists' => $exists],
                "Conexión IMAP correcta. Carpeta «{$this->folder}» con {$exists} mensaje(s)."
            );
        } catch (\Throwable $e) {
            log_message('error', '[ImapMailService] testConnection: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo conectar por IMAP: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Incremental fetch
    // -----------------------------------------------------------------------

    /**
     * Fetches up to $pageSize new messages after $cursor, normalized to the
     * Graph message shape. Returns:
     *   ['success'=>bool, 'messages'=>array, 'count'=>int, 'skipped'=>int,
     *    'cursor'=>?string, 'error'=>?string]
     *
     * Each message is handed to $onMessage as soon as it is normalized and then
     * released, so only one message body is ever held in memory. Callers that
     * pass a consumer get an empty 'messages' and must use 'count'; without a
     * consumer the page is accumulated (kept for ad-hoc/testing use).
     *
     * The returned cursor advances only over the messages included in this page;
     * if more remain, the next run continues from it (bounded, resumable).
     *
     * On the initial pull (no prior UID cursor) a non-empty $sinceDate applies a
     * server-side IMAP SINCE search, so a huge mailbox is not walked from UID 1.
     * SINCE granularity is a whole day; the exact time-of-day cutoff is enforced
     * by the defensive skip at ingestion. Later pages advance by UID only (they
     * are already past the cutoff), so the filter is not re-applied.
     */
    public function fetchPage(?string $cursor, int $pageSize, bool $full, string $sinceDate = '', ?callable $onMessage = null): array
    {
        $client = null;

        try {
            $client = $this->connect();
            $folder = $client->getFolder($this->folder);
            if ($folder === null) {
                $client->disconnect();
                return $this->fail("No se encontró la carpeta IMAP «{$this->folder}».");
            }

            $status      = $folder->examine();
            $uidValidity = (int) ($status['uidvalidity'] ?? 0);

            [$prevValidity, $lastUid] = $this->parseCursor($cursor);
            if ($full || ($prevValidity !== 0 && $prevValidity !== $uidValidity)) {
                $lastUid = 0; // folder rebuilt or forced resync: pull from the start
            }

            // Server-side UID range search, resolved to UIDs only (no bodies).
            // Note the IMAP quirk that "n:*" also returns the highest message
            // when n > max UID, so we defensively drop anything < $next.
            $next  = $lastUid + 1;
            $query = $folder->query()->whereUid($next . ':*');

            // Bound the very first pull by date so a large mailbox is not walked
            // from UID 1. Once we have a UID cursor, whereUid alone keeps us ahead.
            if ($lastUid === 0 && $sinceDate !== '') {
                $query->whereSince($sinceDate);
            }

            $uids = [];
            foreach ($query->search() as $uid) {
                $uid = (int) $uid;
                if ($uid >= $next) {
                    $uids[$uid] = $uid;
                }
            }
            $uids = array_values($uids);
            sort($uids);
            $uids = array_slice($uids, 0, max(1, $pageSize));

            $sizes = $this->fetchSizes($client, $uids);

            $messages = [];
            $count    = 0;
            $skipped  = 0;
            $maxUid   = $lastUid;

            foreach ($uids as $uid) {
                $size    = (int) ($sizes[$uid] ?? 0);
                $oversize = $size > self::MAX_BODY_BYTES;

                try {
                    // One message per FETCH: webklex holds the entire raw
                    // response of a command in memory, so a whole page of
                    // bodies at once is what blew the memory limit.
                    $msg = $folder->query()
                        ->leaveUnread()             // never touch \Seen on a shared box
                        ->setFetchBody(! $oversize)
                        ->setFetchFlags(false)
                        ->getMessageByUid($uid);

                    $normalized = $this->normalize($msg, $uidValidity, $uid, $oversize ? $size : 0);
                    unset($msg);
                } catch (\Throwable $e) {
                    // A single unreadable message must not stall the cursor
                    // forever; report it and move past.
                    $skipped++;
                    $maxUid = max($maxUid, $uid);
                    log_message('error', "[ImapMailService] UID {$uid} omitido: " . $e->getMessage());
                    continue;
                }

                if ($oversize) {
                    $skipped++;
                    log_message('warning', sprintf('[ImapMailService] UID %d sin cuerpo: %d bytes exceden el máximo.', $uid, $size));
                }

                if ($onMessage !== null) {
                    $onMessage($normalized);
                } else {
                    $messages[] = $normalized;
                }
                $count++;
                unset($normalized);

                // webklex messages and attachments reference each other, so
                // refcounting alone will not reclaim them between iterations.
                gc_collect_cycles();

                if ($uid > $maxUid) {
                    $maxUid = $uid;
                }
            }

            $client->disconnect();

            return [
                'success'  => true,
                'messages' => $messages,
                'count'    => $count,
                'skipped'  => $skipped,
                'cursor'   => "UIDVALIDITY:{$uidValidity};UID:{$maxUid}",
                'error'    => null,
            ];
        } catch (\Throwable $e) {
            if ($client !== null) {
                try {
                    $client->disconnect();
                } catch (\Throwable) {
                    // connection already gone; nothing to release
                }
            }
            log_message('error', '[ImapMailService] fetchPage: ' . $e->getMessage());
            return $this->fail('Error al leer por IMAP: ' . $e->getMessage());
        }
    }

    /**
     * RFC822.SIZE for a batch of UIDs, used to decide which bodies are too big
     * to download. Cheap (one small response) and non-fatal: if the server
     * refuses it, every message is treated as normally sized.
     *
     * @return array<int,int> uid => size in bytes
     */
    private function fetchSizes($client, array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        try {
            $sizes = $client->getConnection()->sizes($uids)->validatedData();
            return is_array($sizes) ? $sizes : [];
        } catch (\Throwable $e) {
            log_message('warning', '[ImapMailService] sizes failed: ' . $e->getMessage());
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /** Builds and connects a webklex client from the injected credentials. */
    private function connect()
    {
        $cm = new ClientManager();
        $client = $cm->make([
            'host'          => $this->host,
            'port'          => $this->port,
            'protocol'      => 'imap',
            'encryption'    => $this->encryption === 'none' ? false : $this->encryption,
            'validate_cert' => $this->validateCert,
            'username'      => $this->username,
            'password'      => $this->password,
            'authentication' => null,
        ]);
        $client->connect();
        return $client;
    }

    /**
     * Normalizes a webklex Message into the Graph-shaped array that
     * ConversationService::extract() consumes. Message-ids carry angle brackets
     * to match how the Graph path stores/compares internetMessageId.
     *
     * $oversizeBytes > 0 means the body was deliberately not downloaded: the
     * message is still threaded and listed, with a placeholder body explaining
     * that it has to be opened in the mailbox itself.
     */
    private function normalize(Message $msg, int $uidValidity, int $uid, int $oversizeBytes = 0): array
    {
        $fromAttr  = $msg->from;
        $from      = $fromAttr ? $fromAttr->first() : null;
        $fromEmail = $from ? (string) $from->mail : '';
        $fromName  = $this->decodeMimeHeader($from ? (string) $from->personal : '');

        $toRecipients = $this->addressList($msg->to);
        $ccRecipients = $this->addressList($msg->cc);

        $messageId = trim((string) $msg->message_id);
        $inReplyTo = trim((string) $msg->in_reply_to);
        $references = [];
        $refAttr = $msg->references;
        if ($refAttr !== null) {
            foreach ((array) $refAttr->all() as $r) {
                $r = trim((string) $r);
                if ($r !== '') {
                    $references[] = $r;
                }
            }
        }

        $headers = [];
        if ($inReplyTo !== '') {
            $headers[] = ['name' => 'In-Reply-To', 'value' => $this->wrapId($inReplyTo)];
        }
        if ($references !== []) {
            $headers[] = ['name' => 'References', 'value' => implode(' ', array_map([$this, 'wrapId'], $references))];
        }

        if ($oversizeBytes > 0) {
            $isHtml = false;
            $body   = sprintf(
                'Mensaje demasiado grande para importarse (%s MB). Ábrelo directamente en el buzón para ver su contenido y adjuntos.',
                number_format($oversizeBytes / 1048576, 1)
            );
        } else {
            $isHtml = $msg->hasHTMLBody();
            $body   = $isHtml ? (string) $msg->getHTMLBody() : (string) $msg->getTextBody();
        }

        // Plain-text preview: drops <style>/<script> blocks, tags and entities,
        // and collapses whitespace so no CSS or raw "&nbsp;" leaks into the UI.
        $preview = ForwardParser::plainText($body, 255);

        $dateAttr = $msg->date;
        $iso = '';
        if ($dateAttr !== null && $dateAttr->first() !== null) {
            try {
                $iso = $dateAttr->toDate()->toIso8601String();
            } catch (\Throwable) {
                $iso = '';
            }
        }

        $attachments = $oversizeBytes > 0 ? [] : $this->extractAttachments($msg);

        return [
            'id'                     => 'imap:' . $uidValidity . ':' . $uid,
            'conversationId'         => '', // IMAP has no thread id; threading falls back to References/In-Reply-To
            'internetMessageId'      => $messageId !== '' ? $this->wrapId($messageId) : '',
            'subject'                => $this->decodeMimeHeader((string) $msg->subject),
            'from'                   => ['emailAddress' => ['address' => $fromEmail, 'name' => $fromName]],
            'toRecipients'           => $toRecipients,
            'ccRecipients'           => $ccRecipients,
            'receivedDateTime'       => $iso,
            'bodyPreview'            => $preview,
            'body'                   => ['contentType' => $isHtml ? 'html' : 'text', 'content' => $body],
            'hasAttachments'         => $attachments !== [],
            'attachments'            => $attachments,
            'internetMessageHeaders' => $headers,
            'isDraft'                => false,
        ];
    }

    /**
     * Turns a webklex address attribute (To, Cc, …) into the Graph recipient
     * shape. Null-safe: a header the message does not carry yields [].
     */
    private function addressList(mixed $attribute): array
    {
        $out = [];
        foreach (($attribute ? ($attribute->all() ?: []) : []) as $addr) {
            $mail = (string) ($addr->mail ?? '');
            if ($mail !== '') {
                $out[] = ['emailAddress' => [
                    'address' => $mail,
                    'name'    => $this->decodeMimeHeader((string) ($addr->personal ?? '')),
                ]];
            }
        }

        return $out;
    }

    /**
     * Extracts attachments into the normalized shape consumed by
     * AttachmentService: name, content_type, size, raw content, content_id (for
     * inline cid references) and is_inline.
     */
    private function extractAttachments(Message $msg): array
    {
        $out = [];
        try {
            $attachments = $msg->getAttachments();
        } catch (\Throwable $e) {
            log_message('warning', '[ImapMailService] getAttachments failed: ' . $e->getMessage());
            return [];
        }

        foreach ($attachments as $att) {
            try {
                // Inline only when the part is explicitly inline. A content-id
                // alone does not make it inline (Outlook assigns ids widely); the
                // renderer decides by whether its cid: is actually referenced.
                $disposition = strtolower((string) ($att->disposition ?? ''));
                $contentId   = trim((string) ($att->id ?? ''));
                $isInline    = $disposition === 'inline';

                $content = null;
                try {
                    $content = $att->getContent();
                } catch (\Throwable) {
                    $content = null;
                }
                $size = is_string($content) ? strlen($content) : (int) ($att->getSize() ?: 0);

                $out[] = [
                    'name'         => (string) ($att->getName() ?: ''),
                    'content_type' => (string) ($att->getContentType() ?: ($att->content_type ?? '')),
                    'size'         => $size,
                    'content'      => $content,
                    'content_id'   => $contentId !== '' ? $contentId : null,
                    'is_inline'    => $isInline ? 1 : 0,
                ];
            } catch (\Throwable $e) {
                log_message('warning', '[ImapMailService] attachment parse failed: ' . $e->getMessage());
            }
        }

        return $out;
    }

    /**
     * Decodes RFC 2047 MIME encoded-words (e.g. "=?UTF-8?Q?...?=") to UTF-8.
     * webklex's header decoder leaves some of these untouched, so we normalize
     * subjects and display names here. No-op for plain strings.
     */
    private function decodeMimeHeader(string $value): string
    {
        if ($value === '' || strpos($value, '=?') === false) {
            return $value;
        }
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }
        $mb = @mb_decode_mimeheader($value);
        return $mb !== '' ? $mb : $value;
    }

    /** Wraps a bare message-id in angle brackets (webklex strips them). */
    private function wrapId(string $id): string
    {
        $id = trim($id, " \t\n\r\0\x0B<>");
        return $id !== '' ? '<' . $id . '>' : '';
    }

    /** Parses "UIDVALIDITY:<v>;UID:<u>" -> [validity, lastUid]. */
    private function parseCursor(?string $cursor): array
    {
        if ($cursor === null || $cursor === '') {
            return [0, 0];
        }
        $validity = 0;
        $uid      = 0;
        if (preg_match('/UIDVALIDITY:(\d+)/', $cursor, $m)) {
            $validity = (int) $m[1];
        }
        if (preg_match('/UID:(\d+)/', $cursor, $m)) {
            $uid = (int) $m[1];
        }
        return [$validity, $uid];
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'messages' => [], 'count' => 0, 'skipped' => 0, 'cursor' => null, 'error' => $message];
    }
}
