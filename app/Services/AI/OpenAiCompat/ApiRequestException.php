<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


use RuntimeException;

/**
 * A request that is rejected before it ever reaches the provider - unknown model, missing
 * field, model not released for the API.
 *
 * Carries everything the OpenAI error shape needs, so the controller can report the reason
 * in one place instead of threading error responses back through every helper.
 */
class ApiRequestException extends RuntimeException
{
    public function __construct(
        public readonly int     $status,
        string                  $message,
        public readonly string  $type,
        public readonly ?string $errorCode = null,
        public readonly ?string $param = null,
    )
    {
        parent::__construct($message);
    }

    /**
     * @return array{error: array<string, string>}
     */
    public function toPayload(): array
    {
        return OpenAiError::payload($this->getMessage(), $this->type, $this->errorCode, $this->param);
    }
}
