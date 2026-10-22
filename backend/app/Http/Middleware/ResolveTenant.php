<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\SubscriptionState;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

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
        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant || ! $request->user()?->tenants()->whereKey($tenant->id)->exists()) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'tenant_not_found', 'message' => 'Tenant is unavailable for this token.']]], 404);
        }
        if ($blocked = $this->blockedResponse($request, $tenant)) {
            return $blocked;
        }
        app(TenantContext::class)->set($tenant);
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

    /** Platform-level gates set by the super admin (status) and by billing (expired subscription). */
    private function blockedResponse(Request $request, Tenant $tenant): ?Response
    {
        $write = ! $request->isMethodSafe();
        $error = fn (string $code, string $message, int $status, array $meta = []) => response()->json(['data' => null, 'meta' => $meta ?: (object) [], 'errors' => [['code' => $code, 'message' => $message]]], $status);

        if ($tenant->status === 'hold' && $write) {
            return $error('tenant_on_hold', $tenant->status_reason ?: 'This workshop is on hold. You can view data but not change it.', 423, ['status' => 'hold']);
        }
        if (in_array($tenant->status, ['inactive', 'suspended'], true)) {
            return $error('tenant_'.$tenant->status, $tenant->status_reason ?: 'This workshop is '.$tenant->status.'. Contact support.', 403, ['status' => $tenant->status]);
        }
        if ($write && ! $request->is('api/v1/billing*') && app(SubscriptionState::class)->for($tenant)['locked']) {
            return $error('subscription_expired', 'Your subscription has expired. Renew to make changes.', 402, ['upgrade_required' => true]);
        }

        return null;
    }
}
