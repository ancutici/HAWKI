<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Services\AI\Value\TokenUsage;

class GraylogService
{
    private ?string $host;
    private int $port;

    public function __construct()
    {
        $this->host = config('services.graylog.host') ?: null;
        $this->port = config('services.graylog.port', 10515);
    }

    public function sendChatUsage(
        TokenUsage $usage,
        float $costUsd,
        string $chatType,
        string $employeeType
    ): void {
        if (!$this->host) {
            return;
        }

        // Syslog UDP (RFC 3164), user.info = priority 14
        $msg = sprintf(
            '<%d>%s %s gptalk: model=%s prompt_tokens=%d completion_tokens=%d cost_usd=%.6f chat_type=%s employee_type=%s cache_creation_tokens=%d cache_read_tokens=%d',
            14,
            date('M j H:i:s'),
            gethostname(),
            $usage->model->getId(),
            $usage->promptTokens,
            $usage->completionTokens,
            $costUsd,
            $chatType,
            $employeeType,
            $usage->cacheCreationTokens,
            $usage->cacheReadTokens,
        );

        $sock = @fsockopen('udp://' . $this->host, $this->port, $errno, $errstr, 1);
        if ($sock) {
            @fwrite($sock, $msg);
            fclose($sock);
        }
    }
}
