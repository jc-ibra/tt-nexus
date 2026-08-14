<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use App\Modules\MailDispatch\Services\ForwardParser;
use CodeIgniter\Model;

class MessageModel extends Model
{
    /**
     * Characters of plain text kept per message for searching. Ordinary mail is
     * far below this; the cap exists for the machine-generated outliers (a single
     * shipping notification in the real mailbox yields 733 KB of text) that would
     * otherwise dominate the index for content nobody searches.
     */
    public const BODY_TEXT_LIMIT = 64000;

    /** Tokens honoured per search. Beyond this the query is noise, not intent. */
    private const MAX_TERMS = 8;

    /**
     * Shortest token the engine will index (`innodb_ft_min_token_size`, default
     * 3). Shorter words are dropped from the body search instead of being sent as
     * required terms, which would make every query return nothing.
     */
    private const MIN_TOKEN = 3;

    /** Conversations a single body search may resolve to. */
    private const MAX_MATCHES = 2000;

    protected $table         = 'maildispatch_messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // messages are immutable; only created_at

    protected $allowedFields = [
        'conversation_id',
        'graph_id',
        'internet_message_id',
        'in_reply_to',
        'references_header',
        'direction',
        'from_name',
        'from_email',
        'to_recipients',
        'cc_recipients',
        'subject',
        'body_preview',
        'body',
        'body_is_html',
        'body_text',
        'has_attachments',
        'attachment_names',
        'received_at',
    ];

    /**
     * Derived on the way in, for every insert path (ingestion and both reply
     * services), so no caller can forget it and no message can end up invisible
     * to the search.
     */
    protected $beforeInsert = ['deriveBodyText'];

    /**
     * Per-request memo: rendering the inbox asks the same question twice, once
     * for the list and once for the tab badges. Not invalidated by writes, which
     * is safe because searching only ever happens inside a web/API request; a
     * long-running worker never calls it.
     *
     * @var array<string,array<int,int>>
     */
    private static array $matchMemo = [];

    /**
     * Fills `body_text` from the HTML body unless the caller supplied it (the
     * backfill command writes it directly).
     */
    protected function deriveBodyText(array $data): array
    {
        if (isset($data['data']['body_text'])) {
            return $data;
        }
        $body = (string) ($data['data']['body'] ?? '');
        $data['data']['body_text'] = $body === '' ? '' : ForwardParser::plainText($body, self::BODY_TEXT_LIMIT);

        return $data;
    }

    /** Idempotency guard: has this Graph message already been ingested? */
    public function existsByGraphId(string $graphId): bool
    {
        return $this->where('graph_id', $graphId)->countAllResults() > 0;
    }

    /**
     * Idempotency guard by RFC Message-ID. Catches the case where the same mail
     * arrives under a different backend id — e.g. a reply we sent over SMTP whose
     * forwarded Sent copy re-syncs from IMAP with a fresh UID.
     */
    public function existsByInternetMessageId(string $internetMessageId): bool
    {
        $id = trim($internetMessageId);
        if ($id === '') {
            return false;
        }
        return $this->where('internet_message_id', $id)->countAllResults() > 0;
    }

    /**
     * Detects a second delivery of a mail already stored.
     *
     * When several recipients of the same mail redirect into the shared mailbox,
     * every copy lands with its own Message-ID, so existsByInternetMessageId()
     * misses them and each copy would open its own conversation. The copies do
     * share sender, subject and the exact received instant, which is what this
     * matches on.
     *
     * Deliberately strict: the second, not the minute, because two unrelated
     * mails sharing sender AND subject AND the same second is not a case that
     * happens in practice. The body is NOT compared — verified copies of the
     * same mail differ in size (routing/rendering noise), so a body hash would
     * never match.
     */
    public function existsDuplicateDelivery(string $fromEmail, string $subject, ?string $receivedAt): bool
    {
        $fromEmail = trim($fromEmail);
        $subject   = trim($subject);
        $receivedAt = trim((string) $receivedAt);

        // Any missing piece makes the match unsafe: fall through and let the
        // message in rather than risk dropping a distinct mail.
        if ($fromEmail === '' || $subject === '' || $receivedAt === '') {
            return false;
        }

        return $this->where('from_email', $fromEmail)
            ->where('subject', $subject)
            ->where('received_at', $receivedAt)
            ->countAllResults() > 0;
    }

    /**
     * Threading fallback: resolve the conversation a message belongs to when
     * Graph's conversationId is missing/broken, by matching In-Reply-To /
     * References against an already-stored internet_message_id.
     */
    public function conversationIdForReference(array $messageIds): ?int
    {
        $messageIds = array_values(array_filter(array_map('trim', $messageIds)));
        if ($messageIds === []) {
            return null;
        }
        $row = $this->select('conversation_id')
            ->whereIn('internet_message_id', $messageIds)
            ->orderBy('id', 'DESC')
            ->first();

        return $row ? (int) $row['conversation_id'] : null;
    }

    /** Full thread for a conversation, oldest first. */
    public function forConversation(int $conversationId): array
    {
        return $this->where('conversation_id', $conversationId)
            ->orderBy('received_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    // -----------------------------------------------------------------------
    // Búsqueda en el cuerpo (FULLTEXT sobre body_text)
    // -----------------------------------------------------------------------

    /**
     * Turns what the agent typed into a FULLTEXT boolean expression.
     *
     * Every usable word becomes a required prefix term (`+palabra*`), so typing
     * more words narrows the result instead of widening it, and "pinpad" finds
     * "pinpads". Operator characters are stripped rather than escaped: they are
     * meaningless to someone searching a mailbox and a stray `+` or `~` would
     * otherwise change the query's meaning or break it outright.
     *
     * Returns '' when nothing usable survives, which is the caller's signal to
     * skip the body search entirely.
     */
    public static function booleanExpression(string $q): string
    {
        // Boolean-mode operators and the tokenizer's own separators.
        $clean = preg_replace('/[+\-><()~*"@\\\\]+/u', ' ', trim($q)) ?? '';
        $words = preg_split('/[\s\pP]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < self::MIN_TOKEN) {
                continue;
            }
            $terms[] = '+' . $w . '*';
            if (count($terms) >= self::MAX_TERMS) {
                break;
            }
        }

        return implode(' ', $terms);
    }

    /**
     * Conversations whose body matches, newest first and capped: a single search
     * feeds an `IN (...)` list, so it must stay bounded no matter how generic the
     * term is.
     *
     * @return array<int,int>
     */
    public function conversationIdsMatching(string $q): array
    {
        $expr = self::booleanExpression($q);
        if ($expr === '') {
            return [];
        }
        if (isset(self::$matchMemo[$expr])) {
            return self::$matchMemo[$expr];
        }

        $rows = $this->db->table($this->table)
            ->select('conversation_id')
            ->distinct()
            ->where('MATCH(body_text) AGAINST (' . $this->db->escape($expr) . ' IN BOOLEAN MODE)', null, false)
            ->orderBy('conversation_id', 'DESC')
            ->limit(self::MAX_MATCHES)
            ->get()->getResultArray();

        return self::$matchMemo[$expr] = array_map(static fn($r) => (int) $r['conversation_id'], $rows);
    }

    /**
     * One excerpt per conversation showing why it matched, for the rows on the
     * page being rendered. Scoped to those ids so the cost is bounded by the page
     * size, not by how many conversations matched.
     *
     * @param  array<int,int> $conversationIds
     * @return array<int,string> conversation id => excerpt
     */
    public function snippetsFor(array $conversationIds, string $q, int $radius = 90): array
    {
        $expr = self::booleanExpression($q);
        if ($expr === '' || $conversationIds === []) {
            return [];
        }

        $rows = $this->db->table($this->table)
            ->select('conversation_id, body_text')
            ->whereIn('conversation_id', $conversationIds)
            ->where('MATCH(body_text) AGAINST (' . $this->db->escape($expr) . ' IN BOOLEAN MODE)', null, false)
            ->orderBy('received_at', 'DESC')
            ->get()->getResultArray();

        $terms = array_map(
            static fn(string $t): string => trim($t, '+*'),
            explode(' ', $expr)
        );

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['conversation_id'];
            if (isset($out[$id])) {
                continue; // most recent matching message wins
            }
            $out[$id] = self::excerpt((string) $r['body_text'], $terms, $radius);
        }

        return $out;
    }

    /**
     * A single line around the first term found. The database already proved the
     * text matches; if the exact term is not locatable here (an accent-insensitive
     * hit, or a prefix match on a longer word) the excerpt simply starts at the
     * top rather than showing nothing.
     *
     * @param array<int,string> $terms
     */
    private static function excerpt(string $text, array $terms, int $radius): string
    {
        $text = trim((string) preg_replace('/[\pZ\s]+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        $hay = mb_strtolower($text);
        $at  = null;
        foreach ($terms as $t) {
            if ($t === '') {
                continue;
            }
            $pos = mb_stripos($hay, mb_strtolower($t));
            if ($pos !== false && ($at === null || $pos < $at)) {
                $at = $pos;
            }
        }

        $start = $at === null ? 0 : max(0, $at - $radius);
        $slice = mb_substr($text, $start, $radius * 2);

        return ($start > 0 ? '… ' : '') . $slice . (mb_strlen($text) > $start + $radius * 2 ? ' …' : '');
    }
}
