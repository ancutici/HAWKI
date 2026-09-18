<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


use App\Services\AI\AiService;
use Illuminate\Container\Attributes\Singleton;

/**
 * What the OpenAI-compatible API can actually reach, by kind of model.
 *
 * Two places need the same answer and must not drift apart: GET /api/v1/models, and the
 * info block on the profile page that tells users which ids to put into their client.
 *
 * Chat models come from the model register and carry the release for external applications
 * ('external' in config/model_lists/*.php). Embedding and rerank models are not chat models
 * and are not in the register at all; their allowlists live in config/openai_api.php.
 */
#[Singleton]
readonly class ApiCatalog
{
    public function __construct(
        private AiService $aiService,
    )
    {
    }

    /**
     * Chat models, and the models for the legacy completions endpoint - the same set.
     *
     * Released for external applications AND behind a provider this API can pass through
     * to. Listing a model whose provider speaks a different request format would promise
     * something that answers 403 on the first request.
     *
     * @return array<string, string> model id => provider id
     */
    public function chatModels(): array
    {
        $reachable = (array)config('openai_api.chat_completions_urls', []);
        $models = [];

        foreach ($this->aiService->getAvailableModels(true)->models as $model) {
            $providerId = $model->getProvider()->getConfig()->getId();

            if (!isset($reachable[$providerId])) {
                continue;
            }

            $models[$model->getId()] = $providerId;
        }

        return $models;
    }

    /**
     * @return array<string, string> model id => provider id
     */
    public function embeddingModels(): array
    {
        return $this->allowlist('embeddings');
    }

    /**
     * @return array<string, string> model id => provider id
     */
    public function rerankModels(): array
    {
        return $this->allowlist('rerank');
    }

    /**
     * @return array<string, string> model id => provider id
     */
    private function allowlist(string $section): array
    {
        $config = (array)config('openai_api.' . $section, []);
        $providerId = is_string($config['provider'] ?? null) ? $config['provider'] : 'unknown';
        $models = [];

        foreach ((array)($config['models'] ?? []) as $modelId) {
            if (is_string($modelId) && $modelId !== '') {
                $models[$modelId] = $providerId;
            }
        }

        return $models;
    }
}
