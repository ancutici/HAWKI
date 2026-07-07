<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic;

use App\Models\Attachment;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
readonly class AnthropicRequestConverter
{
    // Anthropic requires max_tokens; use a sensible high default.
    private const DEFAULT_MAX_TOKENS = 8192;

    public function __construct(
        private MessageAttachmentFinder $attachmentFinder
    ) {
    }

    public function convertRequestToPayload(AiRequest $request): array
    {
        $rawPayload = $request->payload;
        $model      = $request->model;
        $messages   = $rawPayload['messages'];
        $modelId    = $rawPayload['model'];

        // Load attachments referenced in messages
        $attachmentsMap = $this->attachmentFinder->findAttachmentsOfMessages($messages);

        // Separate system prompt from conversation messages
        $systemPrompt = null;
        $conversationMessages = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                // Collect all system messages into one string
                $text = $message['content']['text'] ?? (is_string($message['content']) ? $message['content'] : '');
                $systemPrompt = ($systemPrompt ? $systemPrompt . "\n\n" : '') . $text;
            } else {
                $conversationMessages[] = $message;
            }
        }

        // Format conversation messages for the Anthropic Messages API.
        // formatMessage() returns null for turns with no text/attachments (nothing to send);
        // those are dropped rather than sent as an empty block (see formatMessage()).
        $formattedMessages = [];
        foreach ($conversationMessages as $message) {
            $formatted = $this->formatMessage($message, $attachmentsMap, $model);
            if ($formatted !== null) {
                $formattedMessages[] = $formatted;
            }
        }

        // Merge consecutive messages with the same role (Anthropic requires alternating roles)
        $mergedMessages = $this->mergeConsecutiveMessagesWithSameRole($formattedMessages);

        // Prompt caching: reuse the (unchanged) prior turns of this conversation instead of
        // re-billing them as fresh input on every request.
        $this->markCacheBreakpoint($mergedMessages);

        $payload = [
            'model'      => $modelId,
            'max_tokens' => self::DEFAULT_MAX_TOKENS,
            'messages'   => $mergedMessages,
            'stream'     => $rawPayload['stream'] && $model->hasCapability('stream'),
        ];

        if ($systemPrompt !== null && $systemPrompt !== '') {
            // Cached as its own block: the system prompt is identical on every turn of a
            // conversation (and often across conversations/users using the same default prompt).
            $payload['system'] = [[
                'type'          => 'text',
                'text'          => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]];
        }

        // Anthropic does not allow temperature and top_p at the same time; prefer temperature.
        if (isset($rawPayload['params']['temperature'])) {
            $payload['temperature'] = (float)$rawPayload['params']['temperature'];
        } elseif (isset($rawPayload['params']['top_p'])) {
            $payload['top_p'] = (float)$rawPayload['params']['top_p'];
        }

        // Native web search: Anthropic manages the search server-side — no tool-result roundtrip needed.
        if (
            !empty($rawPayload['tools']) &&
            in_array('web_search', $rawPayload['tools'], true) &&
            $model->hasCapability('web_search') &&
            $model->getCapabilityStrategy('web_search') === 'native'
        ) {
            $payload['tools'][] = [
                'type'     => 'web_search_20250305',
                'name'     => 'web_search',
                'max_uses' => 5,
            ];
        }

        return $payload;
    }

    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): ?array
    {
        $role    = $message['role'];
        $content = $message['content'] ?? [];

        $blocks = [];

        if (is_string($content)) {
            $blocks[] = ['type' => 'text', 'text' => $content];
        } else {
            if (!empty($content['text'])) {
                $blocks[] = ['type' => 'text', 'text' => $content['text']];
            }

            if (!empty($content['attachments'])) {
                $this->processAttachments($content['attachments'], $attachmentsMap, $model, $blocks);
            }
        }

        // Anthropic requires content to be a non-empty array AND rejects text blocks whose
        // "text" is itself empty ("text content blocks must be non-empty"). A message with
        // neither text nor attachments carries no information, so drop it instead of sending
        // an empty block — otherwise Anthropic rejects the whole request, and once such a
        // turn is persisted in a conversation's history it poisons every future request there.
        if (empty($blocks)) {
            return null;
        }

        return ['role' => $role, 'content' => $blocks];
    }

    /**
     * Marks the last content block of the second-to-last message with an ephemeral cache
     * breakpoint. Anthropic caches everything up to and including that block, so on the next
     * request (same conversation, one turn later) all prior turns are read from cache instead
     * of billed as fresh input. The final message is always this turn's brand-new input and
     * never repeats verbatim, so it is deliberately left outside the cached prefix.
     */
    private function markCacheBreakpoint(array &$messages): void
    {
        $breakpointIndex = count($messages) - 2;
        if ($breakpointIndex < 0 || empty($messages[$breakpointIndex]['content'])) {
            return;
        }

        $lastBlockIndex = count($messages[$breakpointIndex]['content']) - 1;
        $messages[$breakpointIndex]['content'][$lastBlockIndex]['cache_control'] = ['type' => 'ephemeral'];
    }

    private function mergeConsecutiveMessagesWithSameRole(array $messages): array
    {
        $merged = [];
        foreach ($messages as $msg) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex >= 0 && $merged[$lastIndex]['role'] === $msg['role']) {
                $merged[$lastIndex]['content'] = array_merge(
                    (array)$merged[$lastIndex]['content'],
                    (array)$msg['content']
                );
            } else {
                $merged[] = $msg;
            }
        }
        return $merged;
    }

    private function processAttachments(
        array   $attachmentUuids,
        array   $attachmentsMap,
        AiModel $model,
        array   &$blocks
    ): void {
        $attachmentService  = app(AttachmentService::class);
        $skippedAttachments = [];

        foreach ($attachmentUuids as $uuid) {
            $attachment = $attachmentsMap[$uuid] ?? null;
            if (!$attachment) {
                continue;
            }

            switch ($attachment->type) {
                case 'image':
                    if ($model->canProcessImage()) {
                        $blocks[] = $this->processImageAttachment($attachment, $attachmentService);
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (image not supported)';
                    }
                    break;

                case 'document':
                    if ($model->canProcessDocument()) {
                        $blocks[] = $this->processDocumentAttachment($attachment, $attachmentService);
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (file upload not supported)';
                    }
                    break;

                default:
                    Log::warning('Unknown attachment type: ' . $attachment->type);
                    $skippedAttachments[] = $attachment->name . ' (unsupported type)';
                    break;
            }
        }

        if (!empty($skippedAttachments)) {
            $blocks[] = [
                'type' => 'text',
                'text' => '[NOTE: The following attachments were not included because this model does not support them: '
                    . implode(', ', $skippedAttachments) . ']',
            ];
        }
    }

    private function processImageAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try {
            $binary   = $attachmentService->retrieve($attachment);
            $mime     = $attachment->mime_type ?? 'image/png';
            $base64   = base64_encode($binary);

            return [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mime,
                    'data'       => $base64,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process image attachment: ' . $e->getMessage());
            return ['type' => 'text', 'text' => '[ERROR: Could not process image attachment: ' . $attachment->name . ']'];
        }
    }

    private function processDocumentAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try {
            $fileContent = $attachmentService->retrieve($attachment, 'md');
            $html_safe   = htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8');
            return [
                'type' => 'text',
                'text' => "[ATTACHED FILE: {$attachment->name}]\n---\n{$html_safe}\n---",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process document attachment: ' . $e->getMessage());
            return ['type' => 'text', 'text' => '[ERROR: Could not process document attachment: ' . $attachment->name . ']'];
        }
    }
}
