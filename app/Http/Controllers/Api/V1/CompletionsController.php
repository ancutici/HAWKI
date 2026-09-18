<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;

/**
 * POST /api/v1/completions - the legacy completions endpoint: a raw prompt in, its
 * continuation out, without the roles of a conversation.
 *
 * This is what the development tools use for inline autocompletion. The models that do
 * fill-in-the-middle expect the surrounding code as one prompt, not as a dialogue, which
 * is why the chat endpoint cannot stand in for it - in Continue it is selected with
 * useLegacyCompletionsEndpoint: true.
 *
 * Everything else is identical to chat completions: same models, same release, same
 * streaming, same usage record.
 */
class CompletionsController extends ModelPassthroughController
{
    protected function urlConfigKey(): string
    {
        return 'completions_urls';
    }

    /**
     * @throws ApiRequestException
     */
    protected function validateBody(array $body): void
    {
        $prompt = $body['prompt'] ?? null;

        // A string is the normal case; an array is legitimate too - the endpoint accepts a
        // batch of prompts, and some clients send pre-tokenised input as an array of ints.
        if (is_string($prompt) || (is_array($prompt) && $prompt !== [])) {
            return;
        }

        throw new ApiRequestException(
            422,
            'Field "prompt" is required and must be a string or a non-empty array.',
            OpenAiError::TYPE_INVALID_REQUEST,
            'invalid_request',
            'prompt',
        );
    }
}
