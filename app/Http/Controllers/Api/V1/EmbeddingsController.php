<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;

/**
 * POST /api/v1/embeddings - vector embeddings, passed straight through.
 *
 * This is what the development tools use for codebase retrieval: Continue sends batches of
 * source snippets, typically around thirty per call, and searches the project by comparing
 * the vectors. Without it "@codebase" and every question spanning more than the open file
 * stay unanswered.
 *
 * Two sizes to keep in mind: a batch of snippets is quickly a few hundred kilobytes, so
 * PHP's post_max_size applies, and the answer is several thousand floating point numbers
 * per snippet, which is why it is handed on verbatim instead of being decoded and rebuilt.
 */
class EmbeddingsController extends AuxiliaryPassthroughController
{
    protected function configKey(): string
    {
        return 'embeddings';
    }

    /**
     * @throws ApiRequestException
     */
    protected function validateBody(array $body): void
    {
        $input = $body['input'] ?? null;

        // A single string and an array of strings are both valid; clients use either
        // depending on what they are indexing.
        if (is_string($input) || (is_array($input) && $input !== [])) {
            return;
        }

        throw new ApiRequestException(
            422,
            'Field "input" is required and must be a string or a non-empty array.',
            OpenAiError::TYPE_INVALID_REQUEST,
            'invalid_request',
            'input',
        );
    }
}
