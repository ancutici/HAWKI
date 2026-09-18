<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\AiService;
use App\Services\AI\OpenAiCompat\ApiRequestException;
use App\Services\AI\OpenAiCompat\OpenAiError;
use App\Services\AI\OpenAiCompat\PassthroughClient;
use App\Services\AI\OpenAiCompat\PassthroughResult;
use App\Services\AI\OpenAiCompat\SseUsageSniffer;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\ProviderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared shape of the endpoints that talk to a chat model: chat completions and the legacy
 * completions endpoint.
 *
 * Both take their model from the register and honour the same release for external
 * applications as POST /api/ai-req, both support streaming, and both are billed against
 * the resolved model. They differ only in the field that carries the prompt and in the
 * provider URL they are sent to.
 */
abstract class ModelPassthroughController extends PassthroughController
{
    public function __construct(
        protected readonly AiService $aiService,
        UsageAnalyzerService         $usageAnalyzer,
        PassthroughClient            $passthrough,
    )
    {
        parent::__construct($usageAnalyzer, $passthrough);
    }

    /**
     * The key in config/openai_api.php holding the provider URLs for this endpoint.
     */
    abstract protected function urlConfigKey(): string;

    /**
     * Checks the fields this endpoint needs beyond "model".
     *
     * @throws ApiRequestException
     */
    abstract protected function validateBody(array $body): void;

    public function __invoke(Request $request): Response
    {
        try {
            [$model, $url, $payload, $stream] = $this->prepare($request);
        } catch (ApiRequestException $e) {
            return response()->json($e->toPayload(), $e->status);
        }

        $provider = $model->getProvider()->getConfig();

        return $stream
            ? $this->streamedAnswer($provider, $model, $url, $payload)
            : $this->completeAnswer($provider, $url, $payload, $model);
    }

    /**
     * @return array{0: AiModel, 1: string, 2: array, 3: bool}
     * @throws ApiRequestException
     */
    private function prepare(Request $request): array
    {
        $body = $this->decodeBody($request);
        $modelId = $this->requireModelId($body);

        $this->validateBody($body);

        $model = $this->resolveModel($modelId);
        $url = $this->resolveUrl($model, $modelId);

        $stream = filter_var($body['stream'] ?? false, FILTER_VALIDATE_BOOL);
        if ($stream && !$model->isStreamable()) {
            throw new ApiRequestException(
                400,
                sprintf('The model "%s" does not support streaming. Retry with "stream": false.', $model->getId()),
                OpenAiError::TYPE_INVALID_REQUEST,
                'streaming_not_supported',
                'stream',
            );
        }

        return [$model, $url, $this->buildPayload($body, $model, $stream), $stream];
    }

    /**
     * @throws ApiRequestException
     */
    private function resolveModel(string $modelId): AiModel
    {
        $model = $this->aiService->getModel($modelId, true);
        if ($model !== null) {
            return $model;
        }

        // Separating 'exists but not released' from 'does not exist' tells an administrator
        // reading a client's error message which of the two to change.
        if ($this->aiService->getModel($modelId) !== null) {
            throw new ApiRequestException(
                403,
                sprintf('The model "%s" is not available via the API.', $modelId),
                OpenAiError::TYPE_PERMISSION,
                'model_not_allowed',
                'model',
            );
        }

        throw new ApiRequestException(
            404,
            sprintf('The model "%s" does not exist.', $modelId),
            OpenAiError::TYPE_INVALID_REQUEST,
            'model_not_found',
            'model',
        );
    }

    /**
     * @throws ApiRequestException
     */
    private function resolveUrl(AiModel $model, string $requestedId): string
    {
        $providerId = $model->getProvider()->getConfig()->getId();
        $url = config('openai_api.' . $this->urlConfigKey() . '.' . $providerId);

        if (!is_string($url) || trim($url) === '') {
            // The model is released for external applications but sits behind a provider
            // this route cannot pass through to - OpenAI's Responses API and Anthropic's
            // Messages API both speak a different request format. Nothing a client can fix,
            // so it is logged for the administrator and reported as unavailable.
            Log::warning('Model is released for the API but its provider has no URL for this endpoint', [
                'model' => $model->getId(),
                'provider' => $providerId,
                'endpoint' => $this->urlConfigKey(),
            ]);

            throw new ApiRequestException(
                403,
                sprintf('The model "%s" is not available via the API.', $requestedId),
                OpenAiError::TYPE_PERMISSION,
                'model_not_allowed',
                'model',
            );
        }

        return trim($url);
    }

    private function buildPayload(array $body, AiModel $model, bool $stream): array
    {
        $payload = $this->adaptToModel($this->forwardPayload($body), $model);

        // The model id as configured, not as requested: model lookup also accepts an
        // abbreviated id, and the provider only knows the full one.
        $payload['model'] = $model->getId();
        $payload['stream'] = $stream;

        if ($stream) {
            // Without this the closing chunk carries no token counts on some providers, and
            // the usage record of a streamed request would silently stay empty.
            $payload['stream_options'] = ['include_usage' => true];
        } else {
            unset($payload['stream_options']);
        }

        return $payload;
    }

    /**
     * The two places where a body cannot simply be handed on: a parameter the target model
     * rejects outright, and a parameter the target provider knows under another name.
     *
     * Both would otherwise reach the client as a 400 from the provider, on a request the
     * client had every reason to consider correct - Continue sends temperature and
     * max_tokens for every model in its config. The specification's rule for such fields is
     * to drop them silently and never to reject, which is what happens here. Everything
     * else stays untouched.
     */
    private function adaptToModel(array $payload, AiModel $model): array
    {
        // Reasoning-tier models reject temperature and top_p outright; the model list marks
        // them with tools.temperature = 'unsupported'. Same check as OpenAiRequestConverter
        // makes for the web interface, so both paths treat such a model alike.
        if ($model->getCapabilityStrategy('temperature') === 'unsupported') {
            unset($payload['temperature'], $payload['top_p']);
        }

        $providerId = $model->getProvider()->getConfig()->getId();

        foreach ((array)config('openai_api.parameter_renames.' . $providerId, []) as $from => $to) {
            if (!is_string($to) || !array_key_exists($from, $payload)) {
                continue;
            }

            if (!array_key_exists($to, $payload)) {
                $payload[$to] = $payload[$from];
            }

            unset($payload[$from]);
        }

        return $payload;
    }

    /**
     * The streamed answer: provider chunks are written out as they arrive.
     *
     * Note on error handling: status and headers are on the wire before the first provider
     * byte is read, so a failure that only appears afterwards can no longer be reported as
     * an HTTP status. It is written into the stream instead, which is what the OpenAI
     * clients understand. Everything that can be decided beforehand - authentication,
     * unknown model, missing field - is answered with a proper status code in prepare().
     */
    private function streamedAnswer(ProviderConfig $provider, AiModel $model, string $url, array $payload): StreamedResponse
    {
        $idleTimeout = (int)config('openai_api.stream_idle_timeout', 90);

        return new StreamedResponse(function () use ($provider, $model, $url, $payload, $idleTimeout) {
            // A stream has no overall time limit; it ends when the provider stops delivering
            // for $idleTimeout seconds. Apache's own timeout counts inactivity too, so a
            // stream that keeps sending is never cut by it - unlike a long request without
            // streaming, which is exactly what the 120-second cut-off on glm-4.7 was.
            @set_time_limit(0);

            // PHP-FPM buffers output (output_buffering = 4096). Left in place it would
            // collect the whole answer and deliver it as one block at the end, and the
            // client would show nothing until the model is done.
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $sniffer = new SseUsageSniffer();

            $result = $this->passthrough->stream(
                $provider,
                $url,
                $payload,
                function (string $bytes) use ($sniffer) {
                    $sniffer->feed($bytes);
                    echo $bytes;
                    flush();
                },
                $idleTimeout,
            );

            if ($result->bytesForwarded > 0) {
                $this->recordUsage($model, $sniffer->usage());
            }

            if ($result->isSuccess() && $result->bytesForwarded > 0) {
                // The provider normally closes with 'data: [DONE]'. Only add it if it did
                // not, so clients waiting for the marker do not hang.
                if (!$sniffer->sawDone() && !connection_aborted()) {
                    echo "data: [DONE]\n\n";
                    flush();
                }

                return;
            }

            if (connection_aborted()) {
                Log::info('Client closed a streamed request before it finished', [
                    'model' => $model->getId(),
                    'bytes_forwarded' => $result->bytesForwarded,
                ]);

                return;
            }

            $this->emitStreamError($model, $result, $idleTimeout);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            // Not needed behind this server's Apache, but it costs nothing and is the one
            // lever that switches response buffering off should an nginx ever sit in front.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Writes a failure that surfaced after the stream had already started into the stream.
     */
    private function emitStreamError(AiModel $model, PassthroughResult $result, int $idleTimeout): void
    {
        $response = match (true) {
            $result->curlError !== null => $this->transportError($result, $model, $idleTimeout),
            $result->status >= 400 => $this->upstreamError($result, $model),
            default => $this->emptyAnswerError(),
        };

        echo 'data: ' . $response->getContent() . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
    }
}
