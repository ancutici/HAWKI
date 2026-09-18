<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ChatCompletionsController;
use App\Http\Controllers\Api\V1\CompletionsController;
use App\Http\Controllers\Api\V1\EmbeddingsController;
use App\Http\Controllers\Api\V1\ModelsController;
use App\Http\Controllers\Api\V1\RerankController;
use App\Http\Controllers\Api\V1\UnknownEndpointController;
use App\Http\Controllers\StreamController;
use Illuminate\Http\Request;

use App\Models\User;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

    // ADD OTHER ENDPOINTS HERE


});

/*
 * OpenAI-compatible API.
 *
 * A second entrance into the same machinery as /api/ai-req above, in the format the
 * development tools speak (Continue, Cline, aider, LangChain, LiteLLM). Same Sanctum
 * token, same per-model release for external applications, same usage record - only the
 * request and response format differ, and /api/ai-req stays exactly as it was.
 *
 * openai_errors runs first so it also wraps what the middleware behind it produce:
 * Sanctum's {"message": "Unauthenticated."} and ExternalCommunicationCheck's 403 would
 * otherwise leave the clients with a parse error instead of the reason.
 */
Route::middleware(['openai_errors', 'api_isActive', 'auth:sanctum'])
    ->prefix('v1')
    ->group(function () {

        Route::post('chat/completions', ChatCompletionsController::class);

        // Legacy endpoint for inline autocompletion: a raw prompt instead of a dialogue.
        Route::post('completions', CompletionsController::class);

        Route::post('embeddings', EmbeddingsController::class);

        // Continue builds this URL with new URL("rerank", apiBase), so it has to sit
        // exactly here and nowhere nested deeper.
        Route::post('rerank', RerankController::class);

        Route::get('models', ModelsController::class);

        // Last, so every endpoint above wins: an unknown path under /api/v1/ gets a readable
        // answer naming the endpoints that exist, instead of Laravel's generic
        // "route could not be found" - which the clients report as a wrong base URL.
        Route::any('{path}', UnknownEndpointController::class)->where('path', '.*');

    });
