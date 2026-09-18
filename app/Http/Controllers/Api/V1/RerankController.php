<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;

/**
 * POST /api/v1/rerank - orders documents by relevance to a query, passed straight through.
 *
 * There is no OpenAI standard for reranking, only several dialects (Cohere, Jina, vLLM)
 * that name their fields differently. Rebuilding the answer would mean picking one of them
 * and being wrong for the others, so the provider's answer goes out as it came in.
 *
 * The path matters: Continue builds the URL with new URL("rerank", apiBase), which means
 * the route has to sit exactly at /api/v1/rerank and the client's apiBase needs its
 * trailing slash.
 */
class RerankController extends AuxiliaryPassthroughController
{
    protected function configKey(): string
    {
        return 'rerank';
    }

    /**
     * @throws ApiRequestException
     */
    protected function validateBody(array $body): void
    {
        $query = $body['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            throw new ApiRequestException(
                422,
                'Field "query" is required and must be a string.',
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
                'query',
            );
        }

        $documents = $body['documents'] ?? null;
        if (!is_array($documents) || $documents === []) {
            throw new ApiRequestException(
                422,
                'Field "documents" is required and must be a non-empty array.',
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
                'documents',
            );
        }

        // 'top_n' stays optional and is not filled in here: the provider returns all
        // documents when it is missing, and inventing a value would quietly change what
        // the client asked for.
    }
}
