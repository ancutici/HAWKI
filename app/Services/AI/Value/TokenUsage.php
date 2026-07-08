<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class TokenUsage implements \JsonSerializable
{
    public function __construct(
        public AiModel $model,
        public int     $promptTokens,
        public int     $completionTokens,
        public int     $cacheCreationTokens = 0,
        public int     $cacheReadTokens = 0,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model->getId(),
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'cache_creation_tokens' => $this->cacheCreationTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
}
