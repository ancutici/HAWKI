<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


use App\Services\AI\Value\ProviderConfig;

/**
 * Finds a provider's configuration by its id in model_providers.php.
 *
 * The chat endpoint gets its provider from the resolved model. Embeddings and reranking
 * have no model in the register to ask, so they name their provider in config/openai_api.php
 * and look it up here - only to obtain the API key, which stays inside PassthroughClient.
 */
final class ProviderLookup
{
    public static function find(string $providerId): ?ProviderConfig
    {
        if ($providerId === '') {
            return null;
        }

        $raw = config('model_providers.providers.' . $providerId);
        if (!is_array($raw)) {
            return null;
        }

        $provider = new ProviderConfig($providerId, $raw);

        // An inactive provider is off for the whole application; this API is no exception.
        return $provider->isActive() ? $provider : null;
    }
}
