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
 */
class ImapMailService
{
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
     *   ['success'=>bool, 'messages'=>array, 'cursor'=>?string, 'error'=>?string]
     *
     * The returned cursor advances only over the messages included in this page;
     * if more remain, the next run continues from it (bounded, resumable).
     */
    public function fetchPage(?string $cursor, int $pageSize, bool $full): array
    {
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

            // Server-side UID range search bounded to pageSize: only these bodies
            // are fetched. Note the IMAP quirk that "n:*" also returns the highest
            // message when n > max UID, so we defensively drop anything < $next.
            $next       = $lastUid + 1;
            $collection = $folder->query()
                ->whereUid($next . ':*')
                ->leaveUnread()          // never touch the \Seen flag on a shared box
                ->setFetchBody(true)
                ->setFetchFlags(false)
                ->setFetchOrderAsc()
                ->limit(max(1, $pageSize))
                ->get();

            $items = [];
            foreach ($collection as $msg) {
                if ((int) $msg->uid >= $next) {
                    $items[] = $msg;
                }
            }
            usort($items, static fn(Message $a, Message $b) => (int) $a->uid <=> (int) $b->uid);

            $messages = [];
            $maxUid   = $lastUid;
            foreach ($items as $msg) {
                $uid        = (int) $msg->uid;
                $messages[] = $this->normalize($msg, $uidValidity, $uid);
                if ($uid > $maxUid) {
                    $maxUid = $uid;
                }
            }

            $client->disconnect();

            return [
                'success'  => true,
                'messages' => $messages,
                'cursor'   => "UIDVALIDITY:{$uidValidity};UID:{$maxUid}",
                'error'    => null,
            ];
        } catch (\Throwable $e) {
            log_message('error', '[ImapMailService] fetchPage: ' . $e->getMessage());
            return $this->fail('Error al leer por IMAP: ' . $e->getMessage());
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
     */
    private function normalize(Message $msg, int $uidValidity, int $uid): array
    {
        $fromAttr  = $msg->from;
        $from      = $fromAttr ? $fromAttr->first() : null;
        $fromEmail = $from ? (string) $from->mail : '';
        $fromName  = $this->decodeMimeHeader($from ? (string) $from->personal : '');

        $toRecipients = [];
        $toAttr = $msg->to;
        foreach (($toAttr ? ($toAttr->all() ?: []) : []) as $addr) {
            $mail = (string) ($addr->mail ?? '');
            if ($mail !== '') {
                $toRecipients[] = ['emailAddress' => ['address' => $mail, 'name' => $this->decodeMimeHeader((string) ($addr->personal ?? ''))]];
            }
        }

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

        $isHtml = $msg->hasHTMLBody();
        $body   = $isHtml ? (string) $msg->getHTMLBody() : (string) $msg->getTextBody();

        // Plain-text preview: strip tags, decode HTML entities and collapse
        // whitespace so no raw "&nbsp;"/"&lt;" leaks into the UI.
        $plain   = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain   = trim(preg_replace('/[\pZ\s]+/u', ' ', $plain) ?? $plain);
        $preview = mb_substr($plain, 0, 255);

        $dateAttr = $msg->date;
        $iso = '';
        if ($dateAttr !== null && $dateAttr->first() !== null) {
            try {
                $iso = $dateAttr->toDate()->toIso8601String();
            } catch (\Throwable) {
                $iso = '';
            }
        }

        $attachments = $this->extractAttachments($msg);

        return [
            'id'                     => 'imap:' . $uidValidity . ':' . $uid,
            'conversationId'         => '', // IMAP has no thread id; threading falls back to References/In-Reply-To
            'internetMessageId'      => $messageId !== '' ? $this->wrapId($messageId) : '',
            'subject'                => $this->decodeMimeHeader((string) $msg->subject),
            'from'                   => ['emailAddress' => ['address' => $fromEmail, 'name' => $fromName]],
            'toRecipients'           => $toRecipients,
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
        return ['success' => false, 'messages' => [], 'cursor' => null, 'error' => $message];
    }
}
