<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait AnthropicUsageTrait
{
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usage'])) {
            return null;
        }

        return new TokenUsage(
            model: $model,
            promptTokens: (int)($data['usage']['input_tokens'] ?? 0),
            completionTokens: (int)($data['usage']['output_tokens'] ?? 0),
        );
    }
}
