<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

use Anthropic\Client as AnthropicClient;
use App\Modules\ServiceDesk\Services\ServiceDeskSettings;

/**
 * Optional Claude-powered reformatter for free-text diagnostics and resolution
 * steps (spec §9). The API key and model are REUSED from ServiceDesk's settings
 * (never duplicated); the toggle, max_tokens and system prompt are TechBot's own.
 *
 * Design rule: formatting must never block the flow. If it is disabled, the key
 * is missing, or the call fails/times out, format() returns the original text
 * with ok=false so the caller falls back transparently.
 */
class AiFormatterService
{
    public function __construct(
        private TechBotSettingsService $settings,
        private ServiceDeskSettings $serviceDeskSettings,
    ) {}

    /** Whether reformatting can be attempted at all. */
    public function isAvailable(): bool
    {
        return $this->settings->aiFormattingEnabled()
            && $this->serviceDeskSettings->aiHasApiKey()
            && trim($this->serviceDeskSettings->aiApiKey()) !== '';
    }

    /**
     * Returns a structured result. On any problem it degrades to the original
     * text with ok=false — callers should still offer that text to the user.
     *
     * @return array{ok:bool,formatted:string,original:string,tokens:int,input:int,output:int,model:string,error:?string}
     */
    public function format(string $rawText): array
    {
        $rawText = trim($rawText);
        $model   = $this->serviceDeskSettings->aiModel();
        $fallback = ['ok' => false, 'formatted' => $rawText, 'original' => $rawText, 'tokens' => 0, 'input' => 0, 'output' => 0, 'model' => $model, 'error' => null];

        if ($rawText === '' || ! $this->isAvailable()) {
            return $fallback;
        }

        try {
            $client  = new AnthropicClient(apiKey: $this->serviceDeskSettings->aiApiKey());
            $message = $client->messages->create(
                maxTokens: $this->settings->aiMaxTokens(),
                model: $model,
                system: [[
                    'type' => 'text',
                    'text' => $this->settings->aiSystemPrompt(),
                ]],
                messages: [[
                    'role'    => 'user',
                    'content' => $rawText,
                ]],
            );
        } catch (\Throwable $e) {
            log_message('error', '[TechBot][AI] format failed: ' . $e->getMessage());
            return ['ok' => false, 'formatted' => $rawText, 'original' => $rawText, 'tokens' => 0, 'input' => 0, 'output' => 0, 'model' => $model, 'error' => $e->getMessage()];
        }

        $input  = (int) ($message->usage->inputTokens ?? 0);
        $output = (int) ($message->usage->outputTokens ?? 0);

        $formatted = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $formatted .= $block->text;
            }
        }
        $formatted = trim($formatted);
        if ($formatted === '') {
            return $fallback;
        }

        return [
            'ok'        => true,
            'formatted' => $formatted,
            'original'  => $rawText,
            'tokens'    => $input + $output,
            'input'     => $input,
            'output'    => $output,
            'model'     => $model,
            'error'     => null,
        ];
    }
}
