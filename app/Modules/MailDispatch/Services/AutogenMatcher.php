<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\MailDispatch\Models\AutogenRuleModel;
use App\Modules\MailDispatch\Models\AutogenWhitelistModel;
use App\Modules\MailDispatch\Models\ConversationModel;

/**
 * Evalúa un correo entrante contra las reglas de Autogestión en el momento del
 * ingest. Una regla dispara si: (1) el asunto cumple su patrón, y (2) el
 * remitente/destinatario está en su lista blanca (OBLIGATORIA). Si dispara,
 * parsea el cuerpo `Campo: valor` según el field_map de la regla y decide el
 * estado inicial: 'pending' (listo para crear) o 'review' (faltan datos o
 * excede el rate-limit → a la bandeja sin crear).
 *
 * NO habla con GLPI ni SMTP: solo clasifica. La creación la hace AutogenService
 * desde el worker.
 */
class AutogenMatcher
{
    /** Reglas activas cacheadas para el lote de sync. */
    private ?array $rules = null;
    /** Lista blanca por regla, cacheada. */
    private ?array $whitelist = null;

    public function __construct(
        private AutogenRuleModel $ruleModel,
        private AutogenWhitelistModel $whitelistModel,
        private MailDispatchSettings $settings,
        private ConversationModel $conversations,
        private AutogenAiExtractor $extractor
    ) {}

    /**
     * @param array $f Campos normalizados por ConversationService::extract()
     * @return array|null ['rule','payload','state','missing','note'] o null
     */
    public function match(array $f): ?array
    {
        if (! $this->settings->autogestionEnabled()) {
            return null;
        }
        $this->load();
        if ($this->rules === []) {
            return null;
        }

        $subject   = (string) ($f['subject'] ?? '');
        $fromEmail = (string) ($f['from_email'] ?? '');
        $recipients = trim(((string) ($f['to_email'] ?? '')) . ',' . ((string) ($f['to_recipients'] ?? '')), ',');

        foreach ($this->rules as $rule) {
            if (! $this->subjectMatches($rule, $subject)) {
                continue;
            }
            if (! $this->whitelistMatches($rule, $fromEmail, $recipients)) {
                continue; // lista blanca obligatoria
            }

            $map     = $this->fieldMap($rule);
            $text    = $this->plainText((string) ($f['body'] ?? ''), (int) ($f['body_is_html'] ?? 0) === 1);
            $values  = $this->extractValues($text, $map);
            $payload = $this->composeFromValues($values, $map);
            $ai      = null;

            // Respaldo IA: si el parser no obtuvo los requeridos, la IA rescata
            // los campos del texto libre (solo rellena los que quedaron vacíos).
            if ($payload['missing'] !== [] && $this->extractor->isReady()) {
                $res = $this->extractor->extract($text, $map);
                if ($res['ok']) {
                    foreach ($res['fields'] as $label => $val) {
                        if (($values[$label] ?? '') === '' && $val !== '') {
                            $values[$label] = $val;
                        }
                    }
                    $payload = $this->composeFromValues($values, $map);
                    $ai      = $res;
                }
            }

            $state = 'pending';
            $note  = null;
            if ($payload['missing'] !== []) {
                $state = 'review';
                $note  = 'Faltan datos: ' . implode(', ', $payload['missing']);
            } elseif ($ai !== null && ! $ai['is_request']) {
                $state = 'review';
                $note  = 'La IA no identificó una solicitud de ticket.';
            } elseif ($ai !== null && $ai['confidence'] < $this->settings->autogenAiConfidence()) {
                $state = 'review';
                $note  = sprintf('Confianza de la IA baja (%.2f); revisa antes de crear.', $ai['confidence']);
            } elseif ($this->rateLimited($fromEmail)) {
                $state = 'review';
                $note  = 'Límite de auto-tickets por hora excedido para este remitente.';
            }

            return [
                'rule'    => $rule,
                'payload' => $payload,
                'state'   => $state,
                'missing' => $payload['missing'],
                'note'    => $note,
            ];
        }

        return null;
    }

    // -----------------------------------------------------------------------

    private function load(): void
    {
        if ($this->rules !== null) {
            return;
        }
        $this->rules     = $this->ruleModel->active();
        $ids             = array_map(static fn($r) => (int) $r['id'], $this->rules);
        $this->whitelist = $this->whitelistModel->activeByRule($ids);
    }

    private function subjectMatches(array $rule, string $subject): bool
    {
        $p = mb_strtolower(trim((string) ($rule['subject_pattern'] ?? '')));
        if ($p === '') {
            return false; // autogestión exige un patrón de asunto
        }
        $s = mb_strtolower(trim($subject));
        return ($rule['subject_match_mode'] ?? 'contains') === 'exact'
            ? $s === $p
            : str_contains($s, $p);
    }

    /** Lista blanca obligatoria: sender o recipient debe coincidir. */
    private function whitelistMatches(array $rule, string $fromEmail, string $recipients): bool
    {
        $entries = $this->whitelist[(int) $rule['id']] ?? [];

        // recipient_pattern de la regla cuenta como entrada recipient extra.
        $rp = trim((string) ($rule['recipient_pattern'] ?? ''));
        if ($rp !== '' && $this->addrMatches($rp, $recipients)) {
            return true;
        }

        foreach ($entries as $e) {
            $type   = (string) ($e['type'] ?? 'sender');
            $target = $type === 'recipient' ? $recipients : $fromEmail;
            if ($this->addrMatches((string) $e['value'], $target)) {
                return true;
            }
        }
        return false;
    }

    /** $value es un correo o `@dominio`; $target puede ser una lista con comas. */
    private function addrMatches(string $value, string $target): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return false;
        }
        foreach (explode(',', mb_strtolower($target)) as $t) {
            $t = trim($t);
            if ($t === '') {
                continue;
            }
            if ($v[0] === '@' ? str_ends_with($t, $v) : str_contains($t, $v)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,array{label:string,target:string,required:bool}> */
    private function fieldMap(array $rule): array
    {
        $raw = json_decode((string) ($rule['field_map'] ?? ''), true);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $target = (string) ($row['target'] ?? 'description');
            if (! in_array($target, ['title', 'description', 'ignore'], true)) {
                $target = 'description';
            }
            $out[] = ['label' => $label, 'target' => $target, 'required' => ! empty($row['required'])];
        }
        return $out;
    }

    /** Extrae los valores `Campo: valor` del texto plano según el mapeo. */
    private function extractValues(string $text, array $map): array
    {
        $lines  = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $values = [];
        foreach ($map as $field) {
            $label   = $field['label'];
            $pattern = '/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+?)\s*$/iu';
            $value   = '';
            foreach ($lines as $line) {
                if (preg_match($pattern, $line, $mm)) {
                    $value = trim($mm[1]);
                    break;
                }
            }
            $values[$label] = $value;
        }
        return $values;
    }

    /**
     * Arma el payload (title/description/missing) a partir de los valores.
     * @return array{title:string,description:string,fields:array<string,string>,missing:string[]}
     */
    private function composeFromValues(array $values, array $map): array
    {
        $titleParts = [];
        $descParts  = [];
        $missing    = [];
        foreach ($map as $field) {
            $label = $field['label'];
            $value = $values[$label] ?? '';
            if ($field['required'] && $value === '') {
                $missing[] = $label;
            }
            if ($value === '' || $field['target'] === 'ignore') {
                continue;
            }
            if ($field['target'] === 'title') {
                $titleParts[] = $value;
            } else {
                $descParts[] = $label . ': ' . $value;
            }
        }

        return [
            'title'       => trim(implode(' ', $titleParts)),
            'description' => trim(implode("\n", $descParts)),
            'fields'      => $values,
            'missing'     => $missing,
        ];
    }

    private function plainText(string $body, bool $isHtml): string
    {
        if (! $isHtml) {
            return $body;
        }
        $t = preg_replace('/<(br|\/p|\/div|\/li|\/tr)[^>]*>/i', "\n", $body) ?? $body;
        $t = strip_tags($t);
        return html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function rateLimited(string $fromEmail): bool
    {
        $limit = $this->settings->autogenRateLimitPerHour();
        if ($limit <= 0 || $fromEmail === '') {
            return false;
        }
        $since = date('Y-m-d H:i:s', time() - 3600);
        $count = $this->conversations
            ->where('requester_email', $fromEmail)
            ->where('status', 'autogenerado')
            ->where('received_at >=', $since)
            ->countAllResults();
        return $count >= $limit;
    }
}
