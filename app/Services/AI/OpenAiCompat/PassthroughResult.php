<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


/**
 * Outcome of one upstream call made by PassthroughClient.
 */
readonly class PassthroughResult
{
    public function __construct(
        /**
         * HTTP status of the upstream answer, 0 if the request never got that far.
         */
        public int     $status,
        /**
         * The upstream body. For a streamed call this is empty on success - the bytes went
         * straight to the client - and carries the error body when the upstream refused.
         */
        public string  $body = '',
        public int     $curlErrno = 0,
        public ?string $curlError = null,
        /**
         * Bytes handed to the caller's chunk callback. Zero after a streamed call means the
         * upstream answered without sending anything usable.
         */
        public int     $bytesForwarded = 0,
    )
    {
    }

    public function isTimeout(): bool
    {
        return $this->curlErrno === CURLE_OPERATION_TIMEDOUT;
    }

    public function isSuccess(): bool
    {
        return $this->curlError === null && $this->status > 0 && $this->status < 400;
    }
}
