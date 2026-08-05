<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

/**
 * Parses forwarded emails. When the shared mailbox is fed by a forwarding rule,
 * the SMTP sender is always the forwarder and the real sender lives in the body's
 * "De:/From:" block. These helpers extract that original sender and strip the
 * empty forwarder intro (leading blank space + divider line) from the HTML.
 *
 * Stateless (static) so both ingestion (ConversationService) and rendering can
 * use it without wiring.
 */
class ForwardParser
{
    /**
     * Extracts the original sender from the first "De:"/"From:" line of the body.
     * Returns ['name' => ..., 'email' => ...] or null if not found.
     */
    public static function originalSender(string $body): ?array
    {
        $text = self::toText($body);
        // Only look near the top, where the forwarded header block lives.
        $head = mb_substr($text, 0, 4000);

        if (! preg_match('/^[ \t]*(?:De|From)[ \t]*:[ \t]*(.+)$/mi', $head, $mm)) {
            return null;
        }
        $value = trim($mm[1]);

        // Prefer the address inside angle brackets; fall back to a bare address.
        $email = '';
        $name  = '';
        if (preg_match('/<\s*([^<>@\s]+@[^<>\s]+?)\s*>/', $value, $em)) {
            $email = strtolower(trim($em[1]));
            $name  = trim((string) preg_replace('/<[^>]*>/', '', $value));
        } elseif (preg_match('/([^\s<>,;]+@[^\s<>,;]+)/', $value, $em)) {
            $email = strtolower(trim($em[1]));
            $name  = trim(str_replace($em[1], '', $value));
        }

        $name  = trim($name, " \t\"'");
        $email = rtrim($email, '.,;');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['name' => $name !== '' ? $name : $email, 'email' => $email];
    }

    /**
     * Plain-text snippet of the actual message content. In a forwarded email the
     * body starts with a De:/Enviados:/Para:/Asunto: header block; this skips past
     * the "Asunto:/Subject:" line to the real content. Falls back to the whole
     * text when no forwarded header is present.
     */
    public static function contentSnippet(string $body, int $limit = 200): string
    {
        $text = self::toText($body); // keeps newlines

        if (preg_match('/^[ \t]*(?:De|From)[ \t]*:/mi', $text)
            && preg_match('/^[ \t]*(?:Asunto|Subject)[ \t]*:[^\n]*\n(.*)$/mis', $text, $mm)) {
            $text = $mm[1];
        }

        $text = trim((string) preg_replace('/[\pZ\s]+/u', ' ', $text));
        return mb_substr($text, 0, $limit);
    }

    /**
     * Strips forwarding/reply noise from a subject: leading RV:/FW:/FWD:/RE:/
     * "Reenvío:" prefixes and bracketed [External]/[Externo] tags, in any order
     * or repetition. "RE:" needs the colon, so words like "Reporte" are kept.
     * Returns the original subject if stripping would leave it empty.
     */
    public static function cleanSubject(string $subject): string
    {
        $s       = trim($subject);
        $pattern = '/^\s*(?:\[(?:external|externo|ext)\]\s*|(?:RV|FW|FWD|RE|REENV(?:IO|ÍO)?)\s*:\s*)/iu';
        $prev    = null;
        $i       = 0;
        while ($prev !== $s && $i++ < 15) {
            $prev = $s;
            $s = preg_replace($pattern, '', $s) ?? $s;
        }
        $s = trim($s);
        return $s !== '' ? $s : trim($subject);
    }

    /**
     * Removes the empty forwarder intro at the top of an HTML body: leading
     * whitespace, empty paragraphs/divs, <br>, &nbsp;, and a leading divider
     * (<hr> or an Outlook "divider" line). Best-effort and non-destructive of
     * real content — it only strips leading emptiness.
     */
    public static function stripIntro(string $html): string
    {
        $prev = null;
        $i    = 0;
        while ($prev !== $html && $i++ < 20) {
            $prev = $html;
            $html = preg_replace('/^\s+/u', '', $html) ?? $html;
            $html = preg_replace('/^(?:&nbsp;|&#160;|\x{00A0})+/u', '', $html) ?? $html;
            $html = preg_replace('#^<br\s*/?>#i', '', $html) ?? $html;
            // Empty block wrappers: <p>&nbsp;</p>, <div><br></div>, <o:p></o:p>, <span>&nbsp;</span>
            // (delimiter ~ because the pattern contains a literal '#').
            $html = preg_replace(
                '~^<(p|div|span|o:p)\b[^>]*>(?:\s|&nbsp;|&\#160;|<br\s*/?>|<o:p>|</o:p>)*</\1>~iu',
                '',
                $html
            ) ?? $html;
            // Leading divider line.
            $html = preg_replace('#^<hr\b[^>]*/?>#i', '', $html) ?? $html;
            $html = preg_replace('#^<div\b[^>]*(?:border-top|divider)[^>]*>\s*</div>#i', '', $html) ?? $html;
        }
        return $html;
    }

    /** Normalizes HTML to line-separated plain text for header parsing. */
    private static function toText(string $body): string
    {
        $t = preg_replace('#<br\s*/?>#i', "\n", $body) ?? $body;
        $t = preg_replace('#</(p|div|tr|li|h[1-6])>#i', "\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse runs of spaces/tabs but keep newlines.
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t) ?? $t;
        return $t;
    }
}
