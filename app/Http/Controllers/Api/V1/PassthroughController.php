<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;
use App\Services\AI\OpenAiCompat\PassthroughClient;
use App\Services\AI\OpenAiCompat\PassthroughResult;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\ProviderConfig;
use App\Services\AI\Value\TokenUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the /api/v1/ endpoints have in common: read the request as the client sent it, hand
 * it to the provider, hand the answer back untouched, and record what it cost.
 *
 * The endpoints differ only in which fields they require and where they send them.
 */
abstract class PassthroughController extends Controller
{
    /**
     * Fields that could redirect the request at another target or carry credentials. A
     * client has no business setting these, and the provider must not see them.
     */
    protected const REJECTED_KEYS = [
        'api_key', 'apikey', 'api_base', 'apibase', 'base_url', 'baseurl',
        'endpoint', 'url', 'provider', 'upstream', 'headers', 'extra_headers', 'authorization',
    ];

    public function __construct(
        protected readonly UsageAnalyzerService $usageAnalyzer,
        protected readonly PassthroughClient    $passthrough,
    )
    {
    }

    /**
     * Reads the request body from the raw input instead of $request->json().
     *
     * Laravel's global TrimStrings and ConvertEmptyStringsToNull middleware rewrite the
     * decoded JSON of every request: a trailing newline disappears from a message, and a
     * stop sequence of "\n\n" arrives as null, which the provider answers with an error.
     * Neither is acceptable on a route whose entire purpose is to hand the body on exactly
     * as the client sent it. The raw content is untouched by those middleware.
     *
     * @throws ApiRequestException
     */
    protected function decodeBody(Request $request): array
    {
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ApiRequestException(
                400,
                'The request body is not valid JSON: ' . $e->getMessage(),
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
            );
        }

        if (!is_array($body) || $body === [] || array_is_list($body)) {
            throw new ApiRequestException(
                400,
                'The request body must be a JSON object.',
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
            );
        }

        return $body;
    }

    /**
     * @throws ApiRequestException
     */
    protected function requireModelId(array $body): string
    {
        $modelId = $body['model'] ?? null;

        if (!is_string($modelId) || trim($modelId) === '') {
            throw new ApiRequestException(
                422,
                'Field "model" is required and must be a string.',
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
                'model',
            );
        }

        return trim($modelId);
    }

    /**
     * The client's body as the payload for the provider, minus the fields it may not set.
     *
     * Unknown fields are kept rather than rejected: clients send whatever their version
     * supports, and answering 422 on one of them breaks the integration outright, while the
     * provider simply ignores what it does not know.
     */
    protected function forwardPayload(array $body): array
    {
        $payload = [];

        foreach ($body as $key => $value) {
            if (in_array(strtolower((string)$key), static::REJECTED_KEYS, true)) {
                continue;
            }
            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * One provider call, answer handed on verbatim, usage recorded.
     */
    protected function completeAnswer(ProviderConfig $provider, string $url, array $payload, AiModel $billTo): Response
    {
        $timeout = (int)config('openai_api.request_timeout', 110);
        $result = $this->passthrough->send($provider, $url, $payload, $timeout);

        if ($result->curlError !== null) {
            return $this->transportError($result, $billTo, $timeout);
        }

        if ($result->status >= 400) {
            return $this->upstreamError($result, $billTo);
        }

        if (trim($result->body) === '') {
            Log::error('Provider answered with an empty body', [
                'model' => $billTo->getId(),
                'url' => $url,
                'status' => $result->status,
            ]);

            return $this->emptyAnswerError();
        }

        $this->recordUsage($billTo, $this->usageFromBody($result->body));

        // Verbatim, not re-encoded: every round trip through json_decode/json_encode is a
        // chance to lose a field some client relies on. It also keeps a batch of embeddings
        // - several thousand floating point numbers - from being serialised twice.
        return response($result->body, 200, ['Content-Type' => 'application/json']);
    }

    protected function transportError(PassthroughResult $result, AiModel $model, int $timeout): JsonResponse
    {
        Log::error('Could not reach the provider', [
            'model' => $model->getId(),
            'errno' => $result->curlErrno,
            'error' => $result->curlError,
        ]);

        if ($result->isTimeout()) {
            return response()->json(OpenAiError::payload(
                sprintf(
                    'The model did not answer within %d seconds. Long requests need "stream": true - without streaming the connection is cut before the answer is complete.',
                    $timeout,
                ),
                OpenAiError::TYPE_API,
                'upstream_timeout',
            ), 504);
        }

        // The cURL message names host and port of the provider; that belongs in the log,
        // not in an answer to the client.
        return response()->json(OpenAiError::payload(
            'The model could not be reached. Please try again in a moment.',
            OpenAiError::TYPE_API,
            'upstream_unreachable',
        ), 502);
    }

    protected function upstreamError(PassthroughResult $result, AiModel $model): JsonResponse
    {
        $upstreamMessage = $this->messageFromErrorBody($result->body);

        Log::warning('Provider rejected the request', [
            'model' => $model->getId(),
            'status' => $result->status,
            'message' => $upstreamMessage,
        ]);

        // A 401 or 403 from the provider means GPTalk's own provider key is the problem.
        // Passing that status on would tell the client its token is wrong, which it is not.
        [$status, $type, $code] = match (true) {
            $result->status === 429 => [429, OpenAiError::TYPE_RATE_LIMIT, 'rate_limit_exceeded'],
            $result->status === 401, $result->status === 403 => [502, OpenAiError::TYPE_API, 'upstream_rejected'],
            $result->status >= 500 => [502, OpenAiError::TYPE_API, 'upstream_error'],
            default => [400, OpenAiError::TYPE_INVALID_REQUEST, 'upstream_rejected'],
        };

        $message = match ($status) {
            429 => $upstreamMessage ?? 'Rate limit reached. Please try again in a moment.',
            400 => $upstreamMessage ?? 'The request was rejected by the model.',
            default => 'The model is currently unavailable. Please try again in a moment or switch to another model.',
        };

        return response()->json(OpenAiError::payload($message, $type, $code), $status);
    }

    protected function emptyAnswerError(): JsonResponse
    {
        return response()->json(OpenAiError::payload(
            'The model returned an empty response. Please try again or switch to another model.',
            OpenAiError::TYPE_API,
            'upstream_empty_response',
        ), 502);
    }

    /**
     * Reads a usable message out of a provider error body.
     *
     * Returns null for anything that is not JSON - a reverse proxy's HTML error page must
     * never reach the client, where it would end as a parse error instead of a readable
     * message.
     */
    protected function messageFromErrorBody(string $body): ?string
    {
        if (trim($body) === '' || !json_validate($body)) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        $candidates = [
            is_array($decoded['error'] ?? null) ? ($decoded['error']['message'] ?? null) : null,
            is_string($decoded['error'] ?? null) ? $decoded['error'] : null,
            $decoded['message'] ?? null,
            $decoded['detail'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_substr(trim($candidate), 0, 500);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function usageFromBody(string $body): ?array
    {
        if (!json_validate($body)) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) && !empty($decoded['usage']) && is_array($decoded['usage'])
            ? $decoded['usage']
            : null;
    }

    /**
     * @param array<string, mixed>|null $usage
     */
    protected function recordUsage(AiModel $model, ?array $usage): void
    {
        // No token counts means there is nothing to bill: either the answer carried none, or
        // a streamed request was cut short by the client before the closing chunk arrived.
        if ($usage === null) {
            return;
        }

        $this->usageAnalyzer->submitUsageRecord(
            new TokenUsage(
                model: $model,
                promptTokens: (int)($usage['prompt_tokens'] ?? 0),
                completionTokens: (int)($usage['completion_tokens'] ?? 0),
            ),
            'api',
        );
    }
}
