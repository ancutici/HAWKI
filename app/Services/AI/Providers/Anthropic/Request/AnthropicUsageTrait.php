<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;
use Illuminate\Support\Facades\Log;

trait AnthropicUsageTrait
{
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usage'])) {
            return null;
        }

        $this->logCacheUsage($model, $data['usage']);

        return new TokenUsage(
            model: $model,
            promptTokens: (int)($data['usage']['input_tokens'] ?? 0),
            completionTokens: (int)($data['usage']['output_tokens'] ?? 0),
        );
    }

    /**
     * Temporary diagnostic logging to verify prompt caching is actually hitting/writing —
     * cache_creation_input_tokens/cache_read_input_tokens are not persisted to the DB or
     * shown to users, only logged, since UsageRecord/cost tracking doesn't account for them yet.
     */
    protected function logCacheUsage(AiModel $model, array $usage): void
    {
        $cacheCreation = (int)($usage['cache_creation_input_tokens'] ?? 0);
        $cacheRead     = (int)($usage['cache_read_input_tokens'] ?? 0);

        if ($cacheCreation === 0 && $cacheRead === 0) {
            return;
        }

        Log::info('Anthropic prompt cache usage', [
            'model'                       => $model->getId(),
            'input_tokens'                => (int)($usage['input_tokens'] ?? 0),
            'cache_creation_input_tokens' => $cacheCreation,
            'cache_read_input_tokens'     => $cacheRead,
            'output_tokens'               => (int)($usage['output_tokens'] ?? 0),
        ]);
    }
}
