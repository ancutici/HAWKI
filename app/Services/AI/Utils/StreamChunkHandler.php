<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


class StreamChunkHandler
{
    private string $jsonBuffer = '';
    private string $lineBuffer = '';

    public function __construct(
        private readonly \Closure $onChunk
    )
    {
    }

    public function handle(string $data): void
    {
        // If we already have a partial SSE line in the buffer, append directly without
        // format detection. This reassembles "data: {json}" lines that span multiple
        // curl write callbacks (common for large events like response.completed).
        if (!empty($this->lineBuffer)) {
            $this->lineBuffer .= $data;
            $this->flushLineBuffer();
            return;
        }

        // Fresh data: detect format from the start.
        $trimmedStart = ltrim($data);

        // Google streaming format: raw JSON array without any "data: " / "event: " prefix.
        if (!str_starts_with($trimmedStart, 'data: ') && !str_starts_with($trimmedStart, 'event: ')) {
            $data = $this->normalizeDataChunk($data);
            if (empty($data)) {
                return;
            }
        }

        $this->lineBuffer .= $data;
        $this->flushLineBuffer();
    }

    private function flushLineBuffer(): void
    {
        // Process all complete lines in the buffer. Incomplete trailing lines stay buffered
        // and will be completed when the next curl write callback arrives.
        while (($newlinePos = strpos($this->lineBuffer, "\n")) !== false) {
            if (connection_aborted()) {
                return;
            }

            $line = rtrim(substr($this->lineBuffer, 0, $newlinePos), "\r");
            $this->lineBuffer = substr($this->lineBuffer, $newlinePos + 1);

            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $chunk = substr($line, 6); // strip "data: " prefix
            if (empty($chunk) || !json_validate($chunk)) {
                continue;
            }

            ($this->onChunk)($chunk);
        }
    }
    
    /*
     * Helper function to translate curl return object from google to openai format
     */
    private function normalizeDataChunk(string $data): string
    {
        $this->jsonBuffer .= $data;
        
        if (trim($this->jsonBuffer) === "]") {
            $this->jsonBuffer = "";
            return "";
        }
        
        $output = "";
        while ($extracted = $this->extractJsonObject($this->jsonBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $this->jsonBuffer = $extracted['rest'];
            $output .= "data: " . $jsonStr . "\n";
        }
        return $output;
    }
    
    private function extractJsonObject(string $buffer): ?array
    {
        $openBraces = 0;
        $startFound = false;
        $startPos = 0;
        
        $bufferLength = strlen($buffer);
        for ($i = 0; $i < $bufferLength; $i++) {
            $char = $buffer[$i];
            if ($char === '{') {
                if (!$startFound) {
                    $startFound = true;
                    $startPos = $i;
                }
                $openBraces++;
            } elseif ($char === '}') {
                $openBraces--;
                if ($openBraces === 0 && $startFound) {
                    $jsonStr = substr($buffer, $startPos, $i - $startPos + 1);
                    $rest = substr($buffer, $i + 1);
                    return ['jsonStr' => $jsonStr, 'rest' => $rest];
                }
            }
        }
        return null;
    }
    
    
}
