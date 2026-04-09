<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class AnthropicNonStreamingRequest extends AbstractRequest
{
    use AnthropicUsageTrait;

    public function __construct(
        private array $payload
    ) {
    }

    public function execute(AiModel $model): AiResponse
    {
        $this->payload['stream'] = false;

        return $this->executeNonStreamingRequest(
            model: $model,
            payload: $this->payload,
            getHttpHeaders: fn(AiModel $m) => $this->getAnthropicHeaders($m),
            dataToResponse: fn(array $data) => $this->dataToResponse($model, $data)
        );
    }

    private function dataToResponse(AiModel $model, array $data): AiResponse
    {
        if (isset($data['error'])) {
            $error = $data['error']['message'] ?? 'Unknown Anthropic API error';
            return $this->createErrorResponse($error);
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        $stopReason = $data['stop_reason'] ?? null;

        return new AiResponse(
            content: ['text' => $text],
            usage: $this->extractUsage($model, $data),
            isDone: true,
            error: null,
            finishReason: $stopReason
        );
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
