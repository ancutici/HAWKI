<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


use App\Services\AI\Value\ProviderConfig;
use Illuminate\Container\Attributes\Singleton;
use JsonException;

/**
 * Sends a ready-made OpenAI request body to the upstream provider and hands the answer
 * back untouched.
 *
 * Deliberately not built on AbstractRequest: that class deserialises the provider's answer
 * into an AiResponse for the web interface, which is exactly what must NOT happen here. The
 * clients on this API want the provider's own JSON, field for field.
 *
 * The provider's API key is read from the provider config and never leaves this class.
 */
#[Singleton]
class PassthroughClient
{
    /**
     * Performs a non-streamed call and returns the upstream body verbatim.
     */
    public function send(ProviderConfig $provider, string $url, array $payload, int $timeout): PassthroughResult
    {
        try {
            $encoded = $this->encode($payload);
        } catch (JsonException $e) {
            return new PassthroughResult(status: 0, curlErrno: -1, curlError: 'Payload could not be encoded: ' . $e->getMessage());
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_HTTPHEADER => $this->headers($provider),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : null;
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new PassthroughResult(
            status: $status,
            body: is_string($body) ? $body : '',
            curlErrno: $errno,
            curlError: $error,
        );
    }

    /**
     * Performs a streamed call and hands every byte to $onBytes as it arrives.
     *
     * $onBytes is only called while the upstream is actually streaming. If the upstream
     * refused the request, its body is collected into the result instead, so the caller can
     * turn it into a proper error rather than pushing an error document down a channel the
     * client reads as server-sent events.
     */
    public function stream(ProviderConfig $provider, string $url, array $payload, callable $onBytes, int $idleTimeout): PassthroughResult
    {
        try {
            $encoded = $this->encode($payload);
        } catch (JsonException $e) {
            return new PassthroughResult(status: 0, curlErrno: -1, curlError: 'Payload could not be encoded: ' . $e->getMessage());
        }

        $status = 0;
        $errorBody = '';
        $forwarded = 0;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_HTTPHEADER => $this->headers($provider),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,

            // No overall timeout: a long answer is the normal case here. The stream is
            // aborted only once it stops delivering for $idleTimeout seconds.
            CURLOPT_TIMEOUT => 0,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => $idleTimeout,

            CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$status) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                    $status = (int)$matches[1];
                }

                return strlen($header);
            },

            CURLOPT_WRITEFUNCTION => static function ($ch, string $data) use (&$status, &$errorBody, &$forwarded, $onBytes) {
                // The status line always arrives before the body, so an upstream refusal is
                // known by the time the first byte shows up.
                if ($status >= 400) {
                    $errorBody .= $data;

                    return strlen($data);
                }

                if (connection_aborted()) {
                    return 0;
                }

                $forwarded += strlen($data);
                $onBytes($data);

                return strlen($data);
            },
        ]);

        curl_exec($ch);

        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : null;
        if ($status === 0) {
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);

        // Aborting the transfer from the write callback is how a client disconnect is
        // handled, not a failure worth reporting.
        if ($errno === CURLE_WRITE_ERROR && connection_aborted()) {
            $errno = 0;
            $error = null;
        }

        return new PassthroughResult(
            status: $status,
            body: $errorBody,
            curlErrno: $errno,
            curlError: $error,
            bytesForwarded: $forwarded,
        );
    }

    /**
     * @throws JsonException
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return string[]
     */
    private function headers(ProviderConfig $provider): array
    {
        $headers = [
            'Content-Type: application/json',
        ];

        $apiKey = $provider->getApiKey();
        if ($apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        return $headers;
    }
}
