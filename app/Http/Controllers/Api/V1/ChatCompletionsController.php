<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;

/**
 * POST /api/v1/chat/completions - the OpenAI-compatible entrance to the same machinery
 * that POST /api/ai-req uses.
 *
 * The request is handed to the provider as it came in and the answer goes back untouched.
 * That is possible because the GWDG Academic Cloud speaks this exact API; translating
 * would only risk dropping a field a client depends on. It also means the things the
 * conversion layer does for the web interface do not happen here, which is what the
 * development tools want: reasoning stays in its own field instead of being folded into
 * the answer text, and 'temperature', 'max_tokens' and every other sampling parameter
 * reach the model instead of being filtered out.
 *
 * What is NOT passed through is the request path: authentication, the per-model release
 * for external applications and the usage record all apply exactly as on /api/ai-req.
 */
class ChatCompletionsController extends ModelPassthroughController
{
    protected function urlConfigKey(): string
    {
        return 'chat_completions_urls';
    }

    /**
     * @throws ApiRequestException
     */
    protected function validateBody(array $body): void
    {
        $messages = $body['messages'] ?? null;

        if (!is_array($messages) || $messages === []) {
            throw new ApiRequestException(
                422,
                'Field "messages" is required and must be a non-empty array.',
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
                'messages',
            );
        }

        foreach (array_values($messages) as $index => $message) {
            // 'content' is deliberately left unchecked: a plain string, an array of parts
            // and null (on an assistant message that only carries tool_calls) are all
            // legitimate, and the provider is the authority on what it accepts.
            if (!is_array($message) || !isset($message['role']) || !is_string($message['role'])) {
                throw new ApiRequestException(
                    422,
                    'Every entry in "messages" must be an object with a "role".',
                    OpenAiError::TYPE_INVALID_REQUEST,
                    'invalid_request',
                    sprintf('messages[%d].role', $index),
                );
            }
        }
    }
}
