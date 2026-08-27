<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class RequireVoiceAbility
{
    public function handle(Request $request, Closure $next)
    {
        // Voice endpoints are driven by an external agent, so they must use a
        // dedicated service token — never a browser session. Sanctum issues a
        // TransientToken for stateful/session requests, so reject those too.
        $token = $request->user()?->currentAccessToken();
        if (! $token || $token instanceof TransientToken) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'forbidden', 'message' => 'The voice API requires a service token with the voice:write ability.']]], 403);
        }
        if (! $request->user()->tokenCan('voice:write')) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'forbidden', 'message' => 'The token lacks the voice:write ability.']]], 403);
        }

        return $next($request);
    }
}
