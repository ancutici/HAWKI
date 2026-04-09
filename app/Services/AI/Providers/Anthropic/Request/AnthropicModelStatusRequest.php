<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModelStatusCollection;

class AnthropicModelStatusRequest extends AbstractRequest
{
    public function __construct(
        private readonly ModelProviderInterface $provider
    ) {
    }

    public function execute(AiModelStatusCollection $statusCollection): void
    {
        // Anthropic does not expose a public models/ping endpoint with per-model status.
        // Mark all configured models as online; the actual reachability will surface
        // as an error when a request is made with an invalid key or model id.
        $statusCollection->setAllOnline();
    }
}
