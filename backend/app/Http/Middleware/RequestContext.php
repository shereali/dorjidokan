<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id (honouring an incoming X-Request-Id), puts it in the log context
 * and echoes it on the response, and logs requests slower than monitoring.slow_request_ms.
 */
class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('request_started_at', microtime(true));
        $incoming = (string) $request->header('X-Request-Id');
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : (string) Str::ulid();
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        // terminate() runs on a fresh middleware instance, so the timing and id travel on the request.
        $elapsedMs = (int) round((microtime(true) - (float) $request->attributes->get('request_started_at', microtime(true))) * 1000);
        $threshold = (int) config('monitoring.slow_request_ms', 1500);
        if ($threshold > 0 && $elapsedMs >= $threshold) {
            Log::warning('slow_request', [
                'request_id' => $request->attributes->get('request_id'),
                'method' => $request->method(),
                'route' => $request->route()?->uri() ?? $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $elapsedMs,
                'tenant_id' => app(TenantContext::class)->id(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);
        }
    }
}
