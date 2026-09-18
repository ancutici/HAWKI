<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders an exception raised on a /api/v1/ route in the OpenAI error shape.
 *
 * Wired up in bootstrap/app.php rather than in a middleware, and that is not a matter of
 * taste: Laravel sorts Illuminate\Auth\Middleware\Authenticate to the front of every
 * route's middleware stack by middleware priority, ahead of the ones the route declares.
 * A middleware of the route therefore never sees Sanctum's 401 - it is rendered before the
 * first of them runs. The exception handler is the one place that catches all of them.
 *
 * Error responses that are *returned* rather than thrown - ExternalCommunicationCheck's
 * 403, for one - never reach an exception handler, and are covered by
 * App\Http\Middleware\OpenAiErrorFormat instead.
 */
final class OpenAiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (!$request->is('api/v1/*')) {
            // Not ours: POST /api/ai-req and the web interface keep their existing answers.
            return null;
        }

        if ($e instanceof AuthenticationException) {
            return self::json(401, OpenAiError::payload(
                'Missing or invalid API token.',
                OpenAiError::TYPE_AUTHENTICATION,
                'invalid_api_key',
            ));
        }

        if ($e instanceof ValidationException) {
            [$param, $message] = self::firstValidationFailure($e);

            return self::json(422, OpenAiError::payload(
                $message,
                OpenAiError::TYPE_INVALID_REQUEST,
                'invalid_request',
                $param,
            ));
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = trim($e->getMessage());

            return self::json(
                $status,
                OpenAiError::payload(
                    $message !== '' ? $message : OpenAiError::messageForStatus($status),
                    OpenAiError::typeForStatus($status),
                ),
                // Keeps headers the exception carried, such as Retry-After on a rate limit.
                $e->getHeaders(),
            );
        }

        // Anything unforeseen. The details are in the log - the handler has reported the
        // exception by the time this runs - and must not go out to the client, where they
        // would expose internals of the application.
        return self::json(500, OpenAiError::payload(
            config('app.debug') ? $e->getMessage() : 'An internal error occurred while processing the request.',
            OpenAiError::TYPE_API,
            'internal_error',
        ));
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private static function firstValidationFailure(ValidationException $e): array
    {
        foreach ($e->errors() as $field => $messages) {
            $first = is_array($messages) ? reset($messages) : $messages;
            if (is_string($first) && $first !== '') {
                return [is_string($field) ? $field : null, $first];
            }
        }

        return [null, 'The request could not be processed.'];
    }

    /**
     * @param array{error: array<string, string>} $payload
     * @param array<string, string> $headers
     */
    private static function json(int $status, array $payload, array $headers = []): JsonResponse
    {
        return new JsonResponse($payload, $status, $headers);
    }
}
