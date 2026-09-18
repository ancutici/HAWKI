<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\OpenAiCompat\ApiCatalog;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/models - the models this API can actually reach.
 *
 * Only what is released for external applications, and only what sits behind a provider
 * this API can pass through to. Handing out the whole catalogue would list models that
 * answer 403 on the first request.
 *
 * Continue does not need this - its config names every model explicitly - but Cline,
 * Roo Code, LibreChat and LiteLLM fill their model pickers from it, and it is the quickest
 * way to see from outside what is currently released.
 */
class ModelsController extends Controller
{
    public function __construct(
        private readonly ApiCatalog $catalog,
    )
    {
    }

    public function __invoke(): JsonResponse
    {
        $models = $this->catalog->chatModels();

        if (config('openai_api.list_auxiliary_models', true)) {
            $models += $this->catalog->embeddingModels();
            $models += $this->catalog->rerankModels();
        }

        $data = [];
        foreach ($models as $modelId => $providerId) {
            $data[] = [
                'id' => $modelId,
                'object' => 'model',
                // The schema requires a creation date and GPTalk tracks none. The GWDG
                // answers its own /v1/models with the current time, so the same is done
                // here rather than inventing a date that would read as real.
                'created' => time(),
                'owned_by' => $providerId,
            ];
        }

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ]);
    }
}
