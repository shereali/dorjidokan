<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user?->is_super_admin) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'forbidden', 'message' => 'Super Admin access is required.']]], 403);
        }if (! $user->two_factor_confirmed_at) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'two_factor_required', 'message' => 'Two-factor authentication is required.']]], 403);
        }

        return $next($request);
    }
}
