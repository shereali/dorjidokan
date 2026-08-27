<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasSession() && $request->session()->has('support_impersonation_expires_at') && now()->timestamp >= $request->session()->get('support_impersonation_expires_at')) {
            auth('web')->logout();
            $request->session()->invalidate();

            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'impersonation_expired', 'message' => 'The support impersonation session has expired.']]], 401);
        }
        $slug = $request->header('X-Tenant') ?? explode('.', $request->getHost())[0];
        $tenant = Tenant::where('slug', $slug)->where('status', 'active')->first();
        if (! $tenant || ! $request->user()?->tenants()->whereKey($tenant->id)->exists()) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'tenant_not_found', 'message' => 'Tenant is unavailable for this token.']]], 404);
        } app(TenantContext::class)->set($tenant);
        $previousLocale = App::currentLocale();
        $requestedLocale = strtolower(substr((string) $request->header('Accept-Language'), 0, 2));
        App::setLocale(in_array($requestedLocale, ['bn', 'en'], true) ? $requestedLocale : $tenant->default_locale);
        try {
            return $next($request);
        } finally {
            App::setLocale($previousLocale);
            app(TenantContext::class)->clear();
        }
    }
}
