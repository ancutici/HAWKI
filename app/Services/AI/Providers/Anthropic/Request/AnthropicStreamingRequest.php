<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\TokenUsage;

class AnthropicStreamingRequest extends AbstractRequest
{
    use AnthropicUsageTrait;

    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private int $cacheCreationTokens = 0;
    private int $cacheReadTokens = 0;
    private ?string $stopReason = null;

    public function __construct(
        private array    $payload,
        private \Closure $onData
    ) {
    }

    public function execute(AiModel $model): void
    {
        $this->inputTokens = 0;
        $this->outputTokens = 0;
        $this->cacheCreationTokens = 0;
        $this->cacheReadTokens = 0;
        $this->stopReason = null;

        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: fn(AiModel $m, string $chunk) => $this->chunkToResponse($m, $chunk),
            getHttpHeaders: fn(AiModel $m) => $this->getAnthropicHeaders($m)
        );
    }

    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $data = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);
        $type = $data['type'] ?? '';

        switch ($type) {
            case 'message_start':
                // Input token count (and cache creation/read counts) are in the initial message object
                $usage = $data['message']['usage'] ?? [];
                $this->inputTokens = (int)($usage['input_tokens'] ?? 0);
                $this->cacheCreationTokens = (int)($usage['cache_creation_input_tokens'] ?? 0);
                $this->cacheReadTokens = (int)($usage['cache_read_input_tokens'] ?? 0);
                $this->logCacheUsage($model, $usage);
                return new AiResponse(content: ['text' => ''], isDone: false);

            case 'content_block_delta':
                $text = $data['delta']['text'] ?? '';
                return new AiResponse(content: ['text' => $text], isDone: false);

            case 'message_delta':
                // Output token count and stop reason are in the delta event
                $this->outputTokens = (int)($data['usage']['output_tokens'] ?? 0);
                $this->stopReason = $data['delta']['stop_reason'] ?? null;
                return new AiResponse(content: ['text' => ''], isDone: false);

            case 'message_stop':
                $usage = ($this->inputTokens || $this->outputTokens)
                    ? new TokenUsage(
                        model: $model,
                        promptTokens: $this->inputTokens,
                        completionTokens: $this->outputTokens,
                        cacheCreationTokens: $this->cacheCreationTokens,
                        cacheReadTokens: $this->cacheReadTokens,
                    )
                    : null;
                return new AiResponse(
                    content: ['text' => ''],
                    usage: $usage,
                    isDone: true,
                    error: null,
                    finishReason: $this->stopReason
                );

            case 'error':
                $error = $data['error']['message'] ?? 'Unknown Anthropic streaming error';
                \Log::error('Anthropic streaming error', ['error' => $error]);
                return $this->createErrorResponse($error);

            default:
                // ping, content_block_start, content_block_stop — no action needed
                return new AiResponse(content: ['text' => ''], isDone: false);
        }
    }

    private function getAnthropicHeaders(AiModel $model): array
    {
        return [
            'Content-Type: application/json',
            'x-api-key: ' . $model->getProvider()->getConfig()->getApiKey(),
            'anthropic-version: 2023-06-01',
        ];
    }
}
