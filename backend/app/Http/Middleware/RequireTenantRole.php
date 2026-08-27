<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $tenant = app(TenantContext::class)->get();
        $membership = $request->user()?->tenants()->whereKey($tenant->id)->first();
        if (! $membership || ! in_array($membership->pivot->role, $roles, true)) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [['code' => 'forbidden', 'message' => 'Your workshop role cannot perform this action.']],
            ], 403);
        }

        return $next($request);
    }
}
