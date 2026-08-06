<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use Anthropic\Client as AnthropicClient;
use Throwable;

/**
 * Extractor de IA (Fase 2) — respaldo del parser `Campo: valor`. Cuando el
 * parseo estructurado no obtuvo los campos requeridos, esta clase pide a Claude
 * (SDK Anthropic, misma llave que ServiceDesk) que extraiga los campos del
 * field_map desde el cuerpo libre del correo, con un nivel de confianza y una
 * señal de intención (is_request). No escribe en GLPI: solo extrae datos.
 */
class AutogenAiExtractor
{
    public function __construct(private MailDispatchSettings $settings) {}

    public function isReady(): bool
    {
        return $this->settings->autogenAiReady();
    }

    /**
     * @param string $text  Cuerpo del correo en texto plano.
     * @param array  $map   [{label,target,required}]
     * @return array{ok:bool,fields:array<string,string>,confidence:float,is_request:bool,error?:string}
     */
    public function extract(string $text, array $map): array
    {
        $fail = static fn (string $err): array => ['ok' => false, 'fields' => [], 'confidence' => 0.0, 'is_request' => false, 'error' => $err];

        $labels = [];
        foreach ($map as $f) {
            $l = trim((string) ($f['label'] ?? ''));
            if ($l !== '' && ($f['target'] ?? '') !== 'ignore') {
                $labels[] = $l;
            }
        }
        if ($labels === []) {
            return $fail('El mapeo no tiene campos utilizables.');
        }

        $props = [];
        foreach ($labels as $l) {
            $props[$l] = ['type' => 'string', 'description' => 'Valor de "' . $l . '" tomado del correo, o cadena vacía si no aparece.'];
        }
        $props['confidence'] = ['type' => 'number', 'description' => 'Confianza global de la extracción, de 0 a 1.'];
        $props['is_request'] = ['type' => 'boolean', 'description' => 'true si el correo realmente solicita crear un ticket.'];

        $tool = [
            'name'        => 'extract_ticket',
            'description' => 'Extrae los datos del ticket a partir del correo. Úsala siempre.',
            'inputSchema' => ['type' => 'object', 'properties' => $props, 'required' => ['confidence', 'is_request']],
        ];

        try {
            $client  = new AnthropicClient(apiKey: $this->settings->autogenAiApiKey());
            $message = $client->messages->create(
                maxTokens: $this->settings->autogenAiMaxTokens(),
                model: $this->settings->autogenAiModel(),
                system: [['type' => 'text', 'text' => $this->settings->autogenAiSystemPrompt()]],
                messages: [['role' => 'user', 'content' => mb_substr($text, 0, 8000)]],
                tools: [$tool],
            );
        } catch (Throwable $e) {
            log_message('error', '[MailDispatch][AI] extract failed: ' . $e->getMessage());
            return $fail($e->getMessage());
        }

        $input = null;
        foreach ($message->content as $block) {
            if ($block->type === 'tool_use' && $block->name === 'extract_ticket') {
                $input = (array) $block->input;
                break;
            }
        }
        if ($input === null) {
            return $fail('La IA no devolvió una extracción.');
        }

        $fields = [];
        foreach ($labels as $l) {
            $v = $input[$l] ?? '';
            $fields[$l] = is_string($v) ? trim($v) : '';
        }

        $in  = (int) ($message->usage->inputTokens ?? 0);
        $out = (int) ($message->usage->outputTokens ?? 0);
        log_message('info', sprintf('[MailDispatch][AI] extract conf=%.2f req=%d in=%d out=%d',
            (float) ($input['confidence'] ?? 0), (int) (bool) ($input['is_request'] ?? true), $in, $out));

        return [
            'ok'         => true,
            'fields'     => $fields,
            'confidence' => (float) ($input['confidence'] ?? 0),
            'is_request' => (bool) ($input['is_request'] ?? true),
        ];
    }
}
