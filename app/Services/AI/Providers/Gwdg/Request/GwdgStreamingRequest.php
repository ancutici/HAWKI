<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Tools\Value\ToolCall;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class GwdgStreamingRequest extends AbstractRequest
{
    use GwdgUsageTrait;

    private array $accumulatedToolCalls = [];

    /**
     * True while a <think> block has been opened but not yet closed.
     * Reasoning models stream their chain-of-thought in a separate delta field
     * rather than inline, so the block is reconstructed across chunks.
     */
    private bool $reasoningOpen = false;

    public function __construct(
        private array    $payload,
        private \Closure $onData
    )
    {
    }

    public function execute(AiModel $model): void
    {
        $this->accumulatedToolCalls = [];
        $this->reasoningOpen = false;
//        \Log::info('GwdgStreamingRequest starting execution');
//        \Log::debug($this->payload);

        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: fn(AiModel $m, string $chunk) => $this->chunkToResponse($m, $chunk)
        );
//        \Log::info('GwdgStreamingRequest execution completed');
    }

    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
//        \Log::debug($chunk);
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);

        $finishReason = $jsonChunk['choices'][0]['finish_reason'] ?? null;
        $isDone = in_array($finishReason, ['stop', 'tool_calls', 'length'], true);

        // Handle tool calls in delta
        if (isset($jsonChunk['choices'][0]['delta']['tool_calls'])) {
            $this->processToolCallsDelta($jsonChunk['choices'][0]['delta']['tool_calls']);
        }

        // When done, finalize accumulated tool calls
        $toolCalls = ($isDone && !empty($this->accumulatedToolCalls))
            ? $this->finalizeToolCalls()
            : null;

        // Extract usage data if available (Mistral fix: check for empty choices array)
        $usage = (!empty($jsonChunk['usage']) && empty($jsonChunk['choices']))
            ? $this->extractUsage($model, $jsonChunk)
            : null;

        // Extract content if available
        $delta = $jsonChunk['choices'][0]['delta'] ?? [];
        $content = $this->buildContent($delta, $isDone);

        return new AiResponse(
            content: ['text' => $content],
            usage: $usage,
            isDone: $isDone,
            error: null,
            toolCalls: $toolCalls,
            finishReason: $finishReason
        );
    }

    /**
     * Merges a chunk's reasoning and text deltas into a single content string.
     *
     * Reasoning models served by GWDG (GLM, Qwen) do not emit their chain-of-thought
     * inline. vLLM's reasoning parser splits it into a separate delta field, named
     * either 'reasoning' or 'reasoning_content' depending on the model. Without this
     * mapping the whole thinking phase is dropped, which shows up as a request that
     * streams nothing for minutes — and as an empty answer when generation stops
     * while still reasoning (finish_reason 'length').
     *
     * The thinking text is wrapped in <think> tags so it renders through the think
     * block the frontend already builds for inline-tagged models; unclosed blocks are
     * balanced client-side, so the block grows live while the model is still thinking.
     */
    private function buildContent(array $delta, bool $isDone): string
    {
        $reasoning = (string)($delta['reasoning'] ?? $delta['reasoning_content'] ?? '');
        $text = (string)($delta['content'] ?? '');

        $content = '';

        if ($reasoning !== '') {
            if (!$this->reasoningOpen) {
                $content .= '<think>';
                $this->reasoningOpen = true;
            }
            $content .= $reasoning;
        }

        // Close the block as soon as the answer starts, or if the stream ends mid-thought
        if ($this->reasoningOpen && ($text !== '' || $isDone)) {
            $content .= '</think>';
            $this->reasoningOpen = false;
        }

        return $content . $text;
    }

    /**
     * Process tool calls from a delta chunk
     */
    private function processToolCallsDelta(array $deltaToolCalls): void
    {
        foreach ($deltaToolCalls as $toolCall) {
            $index = $toolCall['index'] ?? 0;

            if (!isset($this->accumulatedToolCalls[$index])) {
                $this->accumulatedToolCalls[$index] = [
                    'id' => $toolCall['id'] ?? null,
                    'type' => $toolCall['type'] ?? 'function',
                    'function' => ['name' => '', 'arguments' => ''],
                ];
            }

            if (isset($toolCall['function']['name'])) {
                $this->accumulatedToolCalls[$index]['function']['name'] .= $toolCall['function']['name'];
            }

            if (isset($toolCall['function']['arguments'])) {
                $this->accumulatedToolCalls[$index]['function']['arguments'] .= $toolCall['function']['arguments'];
            }

            if (isset($toolCall['id'])) {
                $this->accumulatedToolCalls[$index]['id'] = $toolCall['id'];
            }
        }
    }

    /**
     * Finalize accumulated tool calls into ToolCall objects
     */
    private function finalizeToolCalls(): array
    {
        $toolCalls = [];

        foreach ($this->accumulatedToolCalls as $index => $accumulated) {
            try {
                $arguments = json_decode($accumulated['function']['arguments'], true, 512, JSON_THROW_ON_ERROR);

                $toolCalls[] = new ToolCall(
                    id: $accumulated['id'] ?? 'tool-' . $index,
                    type: $accumulated['type'],
                    name: $accumulated['function']['name'],
                    arguments: $arguments ?? [],
                    index: $index
                );

                \Log::info('AiTool call parsed', [
                    'name' => $accumulated['function']['name'],
                    'arguments' => $arguments,
                ]);
            } catch (\JsonException $e) {
                \Log::error('Failed to parse tool call arguments', [
                    'index' => $index,
                    'arguments_string' => $accumulated['function']['arguments'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $toolCalls;
    }
}
