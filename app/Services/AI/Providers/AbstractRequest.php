<?php
declare(strict_types=1);


namespace App\Services\AI\Providers;


use App\Services\AI\Utils\StreamChunkHandler;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use JsonException;

abstract class AbstractRequest
{
    /**
     * Executes a streaming request to the AI model.
     *
     * @param AiModel $model The AI model to interact with.
     * @param array $payload The request payload to send.
     * @param callable(AiResponse $response): void $onData Callback executed for each chunk of data received.
     * @param callable(AiModel $model, string $chunk): AiResponse $chunkToResponse Callback to transform a chunk into a response.
     * @param callable():array|null $getHttpHeaders Optional callback to generate HTTP headers.
     * @param string|null $apiUrl Optional API URL to override the model's default.
     * @param int|null $timeout Optional timeout for the request in seconds.
     * @return void
     */
    protected function executeStreamingRequest(
        AiModel   $model,
        array     $payload,
        callable  $onData,
        callable  $chunkToResponse,
        ?callable $getHttpHeaders = null,
        ?string   $apiUrl = null,
        ?int      $timeout = null
    ): void
    {
        set_time_limit($timeout ?? 120);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl ?? $model->getProvider()->getConfig()->getStreamUrl());

        // Set common cURL options
        $headers = is_callable($getHttpHeaders) ? $getHttpHeaders($model) : $this->getHttpHeaders($model);
        $this->setCommonCurlOptions($ch, $payload, $headers);

        // Collect the response headers; on a rate limit they carry the reset delay.
        $responseHeaders = [];
        $this->setHeaderCollector($ch, $responseHeaders);

        // Set streaming-specific options. A provider can answer HTTP 200 and still send
        // nothing usable, so track whether any chunk actually made it through.
        $chunkReceived = false;
        $this->setStreamingCurlOptions($ch, function (string $chunk) use ($model, $onData, $chunkToResponse, &$chunkReceived) {
            $chunkReceived = true;
            $onData($chunkToResponse($model, $chunk));
        });

        // Execute the cURL session
        $result = curl_exec($ch);

        $curlErrno = curl_errno($ch);
        $curlError = $curlErrno ? curl_error($ch) : null;
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle errors
        if ($curlError !== null) {
            \Log::error('cURL error in streaming request', [
                'error' => $curlError,
                'errno' => $curlErrno,
                'model' => $model->getId(),
                'http_code' => $httpCode,
            ]);

            // A timeout means the provider went silent, which is not an internal fault and
            // is shown to the user — so replace cURL's wording with a readable message.
            $onData($curlErrno === CURLE_OPERATION_TIMEDOUT
                ? $this->createProviderErrorResponse(sprintf(
                    'The model did not respond within %d seconds. It is probably overloaded — please try again or switch to another model.',
                    $this->getStreamIdleTimeout()
                ))
                : $this->createErrorResponse($curlError));
            return;
        }

        if ($result === false) {
            \Log::error('cURL returned false');
        }

        // An HTTP error status is not a cURL error, so it would otherwise pass unnoticed:
        // the body carries no stream chunks, and the request would end as a silent empty
        // answer. Observed with GWDG, whose gateway answers HTTP 500 with an empty body
        // when the model backend is saturated.
        if ($httpCode >= 400) {
            $error = $this->describeHttpError($httpCode, $responseHeaders);
            \Log::error('HTTP error in streaming request', [
                'http_code' => $httpCode,
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getConfig()->getId(),
                'retry_after' => $this->extractRetryAfter($responseHeaders),
            ]);
            $onData($this->createProviderErrorResponse($error));
            return;
        }

        // HTTP 200 without a single usable chunk — same silent-empty-answer symptom.
        if (!$chunkReceived && !connection_aborted()) {
            \Log::error('Empty stream in streaming request', [
                'http_code' => $httpCode,
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getConfig()->getId(),
            ]);
            $onData($this->createProviderErrorResponse(
                'The model returned an empty response. Please try again or switch to another model.'
            ));
        }
    }

    /**
     * Seconds a stream may deliver nothing before it is aborted.
     *
     * @return int
     */
    protected function getStreamIdleTimeout(): int
    {
        return max(1, (int)config('model_providers.streaming.stream_idle_timeout', 90));
    }

    /**
     * Builds a readable message for an HTTP error status.
     *
     * @param int $httpCode The HTTP status code received.
     * @param array<string, string> $responseHeaders Lower-cased response headers.
     * @return string
     */
    protected function describeHttpError(int $httpCode, array $responseHeaders): string
    {
        if ($httpCode === 429) {
            $retryAfter = $this->extractRetryAfter($responseHeaders);

            return $retryAfter !== null
                ? sprintf('Rate limit reached (HTTP 429). Please try again in %d seconds.', $retryAfter)
                : 'Rate limit reached (HTTP 429). Please try again in a moment.';
        }

        if ($httpCode >= 500) {
            return sprintf(
                'The model is currently unavailable (HTTP %d). Please try again in a moment or switch to another model.',
                $httpCode
            );
        }

        return sprintf('The request was rejected by the provider (HTTP %d).', $httpCode);
    }

    /**
     * Reads the retry delay in seconds from the response headers.
     *
     * 'retry-after' is the standard header; GWDG's Kong gateway sends 'ratelimit-reset' instead.
     *
     * @param array<string, string> $responseHeaders Lower-cased response headers.
     * @return int|null
     */
    protected function extractRetryAfter(array $responseHeaders): ?int
    {
        foreach (['retry-after', 'ratelimit-reset', 'x-ratelimit-reset'] as $header) {
            $value = $responseHeaders[$header] ?? null;
            if ($value !== null && is_numeric($value)) {
                return (int)$value;
            }
        }

        return null;
    }

    /**
     * Collects the response headers of a cURL request into $target, keyed by lower-cased name.
     *
     * @param \CurlHandle $ch cURL resource
     * @param array<string, string> $target Filled as the response headers arrive
     * @return void
     */
    protected function setHeaderCollector(\CurlHandle $ch, array &$target): void
    {
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $header) use (&$target) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $target[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($header);
        });
    }

    /**
     * Executes a non-streaming request to the AI model.
     *
     * @param AiModel $model The AI model to interact with.
     * @param array $payload The request payload to send.
     * @param callable(array $data): AiResponse $dataToResponse Callback to transform the data into a response.
     * @param callable|null $getHttpHeaders Optional callback to generate HTTP headers.
     * @param string|null $apiUrl Optional API URL to override the model's default.
     * @param int|null $timeout Optional timeout for the request in seconds.
     * @return AiResponse The response from the AI model.
     * @throws JsonException
     */
    protected function executeNonStreamingRequest(
        AiModel   $model,
        array     $payload,
        callable  $dataToResponse,
        ?callable $getHttpHeaders = null,
        ?string   $apiUrl = null,
        ?int      $timeout = null
    ): AiResponse
    {
        set_time_limit($timeout ?? 120);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl ?? $model->getProvider()->getConfig()->getApiUrl());
        // Set common cURL options
        $headers = is_callable($getHttpHeaders) ? $getHttpHeaders($model) : $this->getHttpHeaders($model);
        $this->setCommonCurlOptions($ch, $payload, $headers);

        // Collect the response headers; on a rate limit they carry the reset delay.
        $responseHeaders = [];
        $this->setHeaderCollector($ch, $responseHeaders);

        // Execute the request
        $response = curl_exec($ch);

        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle errors
        if ($curlError !== null) {
            $error = 'Error: ' . $curlError;
            \Log::error('cURL error in non-streaming request', [
                'error' => $curlError,
                'model' => $model->getId(),
                'http_code' => $httpCode,
            ]);
            return $this->createErrorResponse($error);
        }

        // An HTTP error status carries no usable body — without this check the empty body
        // would only surface as a JsonException from the decode below.
        if ($httpCode >= 400) {
            \Log::error('HTTP error in non-streaming request', [
                'http_code' => $httpCode,
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getConfig()->getId(),
                'retry_after' => $this->extractRetryAfter($responseHeaders),
            ]);
            return $this->createProviderErrorResponse($this->describeHttpError($httpCode, $responseHeaders));
        }

        if (!is_string($response) || trim($response) === '') {
            \Log::error('Empty body in non-streaming request', [
                'http_code' => $httpCode,
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getConfig()->getId(),
            ]);
            return $this->createProviderErrorResponse(
                'The model returned an empty response. Please try again or switch to another model.'
            );
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return $dataToResponse($data);
    }

    /**
     * Create an error response for a failure on the provider's side.
     *
     * Unlike createErrorResponse() this omits the 'INTERNAL ERROR' prefix: an unavailable
     * model or an exhausted rate limit is not a fault of this application, and the text is
     * shown to the user as the assistant's message.
     *
     * @param string $error
     * @return AiResponse
     */
    protected function createProviderErrorResponse(string $error): AiResponse
    {
        return new AiResponse(
            content: [
                'text' => $error,
                'error' => $error
            ],
            error: $error,
        );
    }

    /**
     * Create a standardized error response
     * @param string $error
     * @return AiResponse
     */
    protected function createErrorResponse(string $error): AiResponse
    {
        return new AiResponse(
            content: [
                'text' => 'INTERNAL ERROR: ' . $error,
                'error' => $error
            ],
            error: $error,
        );
    }

    /**
     * Set up common HTTP headers for API requests
     *
     * @param AiModel $model The model to request information for
     * @return array
     */
    protected function getHttpHeaders(AiModel $model): array
    {
        $headers = [
            'Content-Type: application/json'
        ];

        $apiKey = $model->getProvider()->getConfig()->getApiKey();
        // Add authorization header if API key is present
        if ($apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        return $headers;
    }

    /**
     * Set common cURL options for all requests
     *
     * @param \CurlHandle $ch cURL resource
     * @param array $payload Request payload
     * @param array $headers HTTP headers
     * @return void
     */
    protected function setCommonCurlOptions(\CurlHandle $ch, array $payload, array $headers): void
    {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    }

    /**
     * Set up streaming-specific cURL options
     *
     * @param \CurlHandle $ch cURL resource
     * @param callable $onData A callable execute for every chunk received
     * @return void
     */
    protected function setStreamingCurlOptions(\CurlHandle $ch, callable $onData): void
    {
        // Set timeout parameters for streaming
        // LOW_SPEED_TIME is set generously to accommodate reasoning models that may
        // silently think for an extended period before streaming any output — but it
        // must stay below the web server's timeout, so that an unresponsive provider
        // is reported here as a readable error instead of ending as a bare 504.
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->getStreamIdleTimeout());

        $chunkHandler = new StreamChunkHandler($onData);

        // Process each chunk as it arrives
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, $data) use ($chunkHandler) {
            if (connection_aborted()) {
                return 0;
            }

            $chunkHandler->handle($data);

            return strlen($data);
        });
    }
}
