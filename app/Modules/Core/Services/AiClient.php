<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use Anthropic\Client as AnthropicClient;

/**
 * Shared thin wrapper over the Anthropic PHP SDK (anthropic-ai/sdk). Centralizes
 * client construction and the common "system + user message -> text" call with
 * token accounting, so modules don't each re-implement the HTTP/SDK glue.
 *
 * For advanced use (tools, message history, prompt caching) callers can reach
 * the underlying SDK client via raw(); ServiceDesk's tool-based creator can
 * migrate onto raw() over time.
 */
class AiClient
{
    private AnthropicClient $client;

    public function __construct(private string $apiKey)
    {
        $this->client = new AnthropicClient(apiKey: $apiKey);
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /** Underlying SDK client for advanced use (tools, history, cache control). */
    public function raw(): AnthropicClient
    {
        return $this->client;
    }

    /**
     * Simple system + single user message completion returning concatenated
     * text and token usage.
     *
     * @return array{ok:bool,text:string,input_tokens:int,output_tokens:int,error?:string}
     */
    public function completeText(string $model, string $system, string $userMessage, int $maxTokens = 2048): array
    {
        try {
            $message = $this->client->messages->create(
                maxTokens: $maxTokens,
                model: $model,
                system: $system,
                messages: [['role' => 'user', 'content' => $userMessage]],
            );

            $text = '';
            foreach ($message->content as $block) {
                if (($block->type ?? '') === 'text') {
                    $text .= $block->text;
                }
            }

            return [
                'ok'            => true,
                'text'          => trim($text),
                'input_tokens'  => (int) ($message->usage->inputTokens ?? 0),
                'output_tokens' => (int) ($message->usage->outputTokens ?? 0),
            ];
        } catch (\Throwable $e) {
            log_message('error', '[AiClient] completeText failed: ' . $e->getMessage());
            return ['ok' => false, 'text' => '', 'input_tokens' => 0, 'output_tokens' => 0, 'error' => $e->getMessage()];
        }
    }
}
