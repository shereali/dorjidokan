<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `permission:orders.view` or any-of `permission:orders.edit,orders.status`. */
class RequirePermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string ...$required): Response
    {
        $user = $request->user();
        $granted = $user ? $this->permissions->forUser($user, app(TenantContext::class)->get()) : [];
        if (! array_intersect($required, $granted)) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [['code' => 'forbidden', 'message' => 'Your workshop role cannot perform this action.', 'permission' => $required[0] ?? null]],
            ], 403);
        }

        return $next($request);
    }
}
