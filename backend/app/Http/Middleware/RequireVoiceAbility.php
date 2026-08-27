<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireVoiceAbility
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->tokenCan('voice:write')) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'forbidden', 'message' => 'The token lacks the voice:write ability.']]], 403);
        }

        return $next($request);
    }
}
