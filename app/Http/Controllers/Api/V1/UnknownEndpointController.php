<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\OpenAiCompat\OpenAiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

/**
 * Anything under /api/v1/ that no endpoint above matched.
 *
 * Without this route Laravel answers its own {"message": "The route api/v1/... could not be
 * found."}, which the clients show as an invalid base URL - and the base URL is usually
 * right. The real cause is that the client chose an endpoint this API does not offer, and
 * the answer should say which ones it does.
 *
 * The prominent case is the Responses API: Continue, Codex and the newer OpenAI SDKs decide
 * by model name that a reasoning model has to go to /v1/responses, so a released gpt-5 model
 * fails on a route that was never requested by hand.
 */
class UnknownEndpointController extends Controller
{
    public function __construct(
        private readonly Router $router,
    )
    {
    }

    public function __invoke(Request $request, string $path = ''): JsonResponse
    {
        $path = trim($path, '/');
        $endpoints = $this->knownEndpoints();

        // The catch-all also swallows a known path called with the wrong verb, because it
        // matches any method. Saying "unknown endpoint" would send the reader looking for a
        // spelling mistake that is not there.
        if (isset($endpoints[$path])) {
            return $this->error(
                sprintf(
                    'Endpoint "/api/v1/%s" does not accept %s. Allowed: %s.',
                    $path,
                    $request->getMethod(),
                    implode(', ', $endpoints[$path]),
                ),
                'method_not_allowed',
                405,
            );
        }

        $message = sprintf(
            'Unknown endpoint "/api/v1/%s". This API speaks the OpenAI chat completions format; available: %s.',
            $path,
            implode(', ', array_keys($endpoints)),
        );

        if (str_starts_with($path, 'responses')) {
            $message .= ' The Responses API (/v1/responses) is not offered.'
                . ' Clients that choose it by model name can be pointed back at chat completions:'
                . ' in Continue with "useResponsesApi: false" in the model block.';
        }

        return $this->error($message, 'unknown_endpoint', 404);
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json(
            OpenAiError::payload($message, OpenAiError::TYPE_INVALID_REQUEST, $code),
            $status,
        );
    }

    /**
     * Read from the route table rather than written out here, so the list cannot fall behind
     * the endpoints that actually exist.
     *
     * @return array<string, string[]> path below /api/v1/ => allowed methods
     */
    private function knownEndpoints(): array
    {
        $endpoints = [];

        foreach ($this->router->getRoutes() as $route) {
            $uri = $route->uri();

            // Skip this catch-all itself - it is the only route here carrying a parameter.
            if (!str_starts_with($uri, 'api/v1/') || str_contains($uri, '{')) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $path = substr($uri, strlen('api/v1/'));

            $endpoints[$path] = array_values(array_unique(array_merge($endpoints[$path] ?? [], $methods)));
        }

        ksort($endpoints);

        return $endpoints;
    }
}
