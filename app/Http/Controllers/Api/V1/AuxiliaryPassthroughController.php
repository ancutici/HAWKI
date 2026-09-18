<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;
use App\Services\AI\OpenAiCompat\ProviderLookup;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\ProviderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared shape of the endpoints that are not chat: embeddings and reranking.
 *
 * Both take a model from an allowlist in config/openai_api.php rather than from the model
 * register, because neither is a chat model - putting them into config/model_lists/*.php
 * would make them show up in the web interface's model picker, where they cannot be used.
 *
 * Their answer formats differ between providers and dialects (Cohere, Jina, vLLM name
 * their fields differently), which is the reason they are passed through rather than
 * rebuilt: what the provider sends is what the client was written against.
 */
abstract class AuxiliaryPassthroughController extends PassthroughController
{
    /**
     * The section in config/openai_api.php that holds url, provider and allowlist.
     */
    abstract protected function configKey(): string;

    /**
     * Checks the fields this endpoint needs beyond "model".
     *
     * @throws ApiRequestException
     */
    abstract protected function validateBody(array $body): void;

    public function __invoke(Request $request): Response
    {
        try {
            $body = $this->decodeBody($request);
            $modelId = $this->requireModelId($body);
            $this->validateBody($body);

            [$url, $provider] = $this->resolveTarget($modelId);
        } catch (ApiRequestException $e) {
            return response()->json($e->toPayload(), $e->status);
        }

        $payload = $this->forwardPayload($body);
        $payload['model'] = $modelId;

        return $this->completeAnswer($provider, $url, $payload, $this->billingModel($modelId));
    }

    /**
     * @return array{0: string, 1: ProviderConfig}
     * @throws ApiRequestException
     */
    private function resolveTarget(string $modelId): array
    {
        $section = config('openai_api.' . $this->configKey(), []);
        $allowed = is_array($section['models'] ?? null) ? $section['models'] : [];

        // Exact match only. Unlike the chat models there is no abbreviated form to resolve,
        // and a fuzzy match would silently route a request at a model nobody released.
        if (!in_array($modelId, $allowed, true)) {
            throw new ApiRequestException(
                403,
                sprintf('The model "%s" is not available via the API.', $modelId),
                OpenAiError::TYPE_PERMISSION,
                'model_not_allowed',
                'model',
            );
        }

        $url = $section['url'] ?? null;
        $providerId = $section['provider'] ?? null;
        $provider = ProviderLookup::find(is_string($providerId) ? $providerId : '');

        if (!is_string($url) || trim($url) === '' || $provider === null) {
            // The model is on the allowlist but the endpoint behind it is not configured or
            // its provider is switched off. An administrator has to fix that, not a client.
            Log::error('Endpoint is on the allowlist but not usable', [
                'endpoint' => $this->configKey(),
                'model' => $modelId,
                'provider' => $providerId,
                'url_configured' => is_string($url) && trim($url) !== '',
            ]);

            throw new ApiRequestException(
                503,
                'This endpoint is not configured. Please contact the administration.',
                OpenAiError::TYPE_API,
                'endpoint_not_configured',
            );
        }

        return [trim($url), $provider];
    }

    /**
     * The model identity the usage record is written against.
     *
     * These models are not in the register, so there is no AiModel to hand over. The usage
     * record and the cost calculation only ever read the id, so one carrying nothing else
     * is enough - and it keeps the billing of these endpoints in the same table and under
     * the same type ('api') as the chat requests.
     */
    private function billingModel(string $modelId): AiModel
    {
        return new AiModel(['id' => $modelId]);
    }
}
