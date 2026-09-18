<?php
declare(strict_types=1);


namespace App\Services\AI\OpenAiCompat;


/**
 * Reads along while a stream is passed through to the client, without touching it.
 *
 * Two things have to be known once a stream is over that are not visible from the outside:
 * the token counts, which arrive in the very last chunk and are what the usage record is
 * built from, and whether the upstream closed the stream properly with 'data: [DONE]'.
 *
 * Chunk boundaries fall wherever the network puts them, so a 'data:' line can be split
 * across two calls to feed(); incomplete lines stay buffered until they are complete.
 */
class SseUsageSniffer
{
    /**
     * Cap for the partial-line buffer. An SSE line is a single JSON chunk and stays far
     * below this; the limit only stops a malformed stream without newlines from growing
     * without bound.
     */
    private const MAX_BUFFER = 1_048_576;

    private string $buffer = '';
    private ?array $usage = null;
    private bool $sawDone = false;

    public function feed(string $bytes): void
    {
        $this->buffer .= $bytes;

        while (($newlinePos = strpos($this->buffer, "\n")) !== false) {
            $line = rtrim(substr($this->buffer, 0, $newlinePos), "\r");
            $this->buffer = substr($this->buffer, $newlinePos + 1);
            $this->inspect($line);
        }

        if (strlen($this->buffer) > self::MAX_BUFFER) {
            $this->buffer = '';
        }
    }

    /**
     * The token counts of the last chunk that carried any, or null if none did.
     *
     * @return array<string, mixed>|null
     */
    public function usage(): ?array
    {
        return $this->usage;
    }

    /**
     * Whether the upstream ended the stream with 'data: [DONE]'.
     */
    public function sawDone(): bool
    {
        return $this->sawDone;
    }

    private function inspect(string $line): void
    {
        if (!str_starts_with($line, 'data: ')) {
            return;
        }

        $chunk = trim(substr($line, 6));

        if ($chunk === '[DONE]') {
            $this->sawDone = true;

            return;
        }

        if ($chunk === '' || !json_validate($chunk)) {
            return;
        }

        $decoded = json_decode($chunk, true);
        if (is_array($decoded) && !empty($decoded['usage']) && is_array($decoded['usage'])) {
            $this->usage = $decoded['usage'];
        }
    }
}
