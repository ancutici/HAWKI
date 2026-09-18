<?php

namespace App\Http\Middleware;

use App\Services\AI\OpenAiCompat\OpenAiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rewrites every error answer of the /api/v1/ routes into the OpenAI error shape.
 *
 * The clients parse {"error": {"message": ...}} and show nothing useful for anything else:
 * Sanctum answers {"message": "Unauthenticated."}, ExternalCommunicationCheck answers
 * {"response": "..."} and the validator answers {"message": "Validation Error", "errors": {...}}.
 *
 * This runs as the outermost middleware of the group so it also catches what the middleware
 * behind it produce - Laravel's pipeline renders an exception where it is thrown and passes
 * the finished response back out through the middleware in front of it.
 *
 * Only for /api/v1/*; POST /api/ai-req keeps its existing answers unchanged.
 */
class OpenAiErrorFormat
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            return $response;
        }

        // A stream has its status and headers on the wire long before the body is produced,
        // so there is nothing left to rewrite here. The controller reports a failure that
        // surfaces mid-stream as an error event inside the stream instead.
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        $status = $response->getStatusCode();
        $body = $this->decodeBody($response);

        // Already in the OpenAI shape - the controllers build their errors themselves,
        // where the specific type and code are known.
        if (isset($body['error']) && is_array($body['error']) && isset($body['error']['message'])) {
            return $response;
        }

        $payload = OpenAiError::payload(
            $this->extractMessage($body, $status),
            OpenAiError::typeForStatus($status),
            $status === 401 ? 'invalid_api_key' : null,
        );

        $response->setContent(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBody(Response $response): ?array
    {
        $content = $response->getContent();
        if (!is_string($content) || $content === '' || !json_validate($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function extractMessage(?array $body, int $status): string
    {
        if ($body === null) {
            return OpenAiError::messageForStatus($status);
        }

        // The validator puts the reason into 'errors' and leaves 'message' as the generic
        // 'Validation Error', so the first field message is the one worth showing.
        if (isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $messages) {
                $first = is_array($messages) ? reset($messages) : $messages;
                if (is_string($first) && $first !== '') {
                    return is_string($field) ? $field . ': ' . $first : $first;
                }
            }
        }

        foreach (['message', 'response', 'error'] as $key) {
            $value = $body[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return OpenAiError::messageForStatus($status);
    }
}
