<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


/**
 * Builds error bodies in the shape the OpenAI clients expect:
 *
 *   {"error": {"message": "...", "type": "invalid_request_error", "code": "model_not_found"}}
 *
 * Laravel's own error bodies ({"message": "Unauthenticated."}, the validator's
 * {"message": "Validation Error", "errors": {...}}) make the clients report a parse
 * failure instead of the actual reason, so everything leaving /api/v1/ is wrapped
 * into this shape - see App\Http\Middleware\OpenAiErrorFormat.
 */
final class OpenAiError
{
    public const TYPE_INVALID_REQUEST = 'invalid_request_error';
    public const TYPE_AUTHENTICATION = 'authentication_error';
    public const TYPE_PERMISSION = 'permission_error';
    public const TYPE_RATE_LIMIT = 'rate_limit_error';
    public const TYPE_API = 'api_error';

    /**
     * @return array{error: array{message: string, type: string, param?: string, code?: string}}
     */
    public static function payload(string $message, string $type, ?string $code = null, ?string $param = null): array
    {
        $error = [
            'message' => $message,
            'type' => $type,
        ];

        if ($param !== null) {
            $error['param'] = $param;
        }
        if ($code !== null) {
            $error['code'] = $code;
        }

        return ['error' => $error];
    }

    /**
     * The error type that matches an HTTP status, used where no more specific one is known.
     */
    public static function typeForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => self::TYPE_AUTHENTICATION,
            $status === 403 => self::TYPE_PERMISSION,
            $status === 429 => self::TYPE_RATE_LIMIT,
            $status >= 500 => self::TYPE_API,
            default => self::TYPE_INVALID_REQUEST,
        };
    }

    /**
     * A readable fallback message for a status, used when the original body carried none.
     */
    public static function messageForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => 'Missing or invalid API token.',
            $status === 403 => 'Access to this resource is not permitted.',
            $status === 404 => 'The requested resource does not exist.',
            $status === 429 => 'Rate limit reached. Please try again in a moment.',
            $status >= 500 => 'The service is temporarily unavailable. Please try again in a moment.',
            default => 'The request could not be processed.',
        };
    }
}
