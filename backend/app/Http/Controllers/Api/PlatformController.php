<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformMutationRequest;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\FeatureGateService;
use App\Services\TenantProvisioner;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    public function register(PlatformMutationRequest $r, TenantProvisioner $p): JsonResponse
    {
        $d = $r->validated();
        [$tenant,$user] = $p->create($d);
        abort_unless($r->hasSession(), 400, 'This onboarding endpoint requires a stateful SPA request.');
        Auth::guard('web')->login($user);
        $r->session()->regenerate();

        return $this->ok(['authenticated' => true, 'tenant' => ['id' => $tenant->public_id, 'name' => $tenant->name, 'slug' => $tenant->slug], 'user' => ['name' => $user->name, 'role' => 'admin']], [], 201);
    }

    public function plans(): JsonResponse
    {
        return $this->ok(['plans' => Plan::where('active', true)->orderBy('price_minor')->get()->map(fn ($plan) => ['code' => $plan->code, 'name' => $plan->name, 'price_minor' => $plan->price_minor, 'currency' => $plan->currency, 'billing_interval' => $plan->billing_interval, 'feature_limits' => $plan->feature_limits])]);
    }

    public function billing(FeatureGateService $gate): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $subscription = $tenant->subscriptions()->latest('id')->first();

        $plan = $subscription?->plan_id ? Plan::find($subscription->plan_id) : null;

        return $this->ok(['subscription' => $subscription ? ['status' => $subscription->stripe_status ?: $subscription->status, 'plan' => $plan ? ['code' => $plan->code, 'name' => $plan->name] : null, 'trial_ends_at' => $subscription->trial_ends_at, 'ends_at' => $subscription->ends_at, 'grace_ends_at' => $subscription->grace_ends_at] : null, 'limits' => $gate->limits(), 'payment_method' => $tenant->pm_last_four ? ['type' => $tenant->pm_type, 'last_four' => $tenant->pm_last_four] : null]);
    }

    public function portal(): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        if (! $tenant->stripe_id || ! config('cashier.secret')) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'billing_not_configured', 'message' => 'Stripe billing is not configured.']]], 503);
        }

        return $this->ok(['url' => $tenant->billingPortalUrl(config('app.frontend_url', 'http://localhost:3000'))]);
    }

    public function checkout(PlatformMutationRequest $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $plan = Plan::where('code', $request->validated('plan_code'))->where('active', true)->firstOrFail();
        $price = $plan->feature_limits['stripe_price_id'] ?? null;
        if (! $price || ! config('cashier.secret')) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'billing_not_configured', 'message' => 'This plan is not connected to a Stripe price.']]], 503);
        }
        $frontend = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        $checkout = $tenant->newSubscription('default', $price)->checkout(['success_url' => $frontend.'/?billing=success', 'cancel_url' => $frontend.'/?billing=cancelled']);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'billing.checkout_created', 'subject_type' => Plan::class, 'subject_id' => $plan->id, 'metadata' => ['plan' => $plan->code]]);

        return $this->ok(['url' => $checkout->url]);
    }

    public function members(): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        return $this->ok(['items' => $tenant->users()->orderBy('name')->get()->map(fn (User $user) => $this->member($user))]);
    }

    public function saveMember(PlatformMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenant = app(TenantContext::class)->get();
        $user = DB::transaction(function () use ($data, $tenant) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::password(40)]);
            $tenant->users()->attach($user->id, ['role' => $data['role']]);

            return $user;
        });
        $user->setRelation('pivot', (object) ['role' => $data['role']]);
        $user->notify(new ResetPasswordNotification(Password::broker()->createToken($user), $tenant->slug));

        return $this->ok(['member' => $this->member($user), 'invitation_sent' => true], [], 201);
    }

    public function updateMember(PlatformMutationRequest $request, User $user): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant->users()->whereKey($user->id)->exists(), 404);
        $role = $request->validated('role');
        if ($request->user()->is($user) && $role !== 'admin') {
            return $this->validationError('role', 'You cannot remove your own administrator role.');
        }
        $tenant->users()->updateExistingPivot($user->id, ['role' => $role]);
        $user->setRelation('pivot', (object) ['role' => $role]);

        return $this->ok(['member' => $this->member($user)]);
    }

    public function deleteMember(Request $request, User $user): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant->users()->whereKey($user->id)->exists(), 404);
        if ($request->user()->is($user)) {
            return $this->validationError('member', 'You cannot remove yourself from the workshop.');
        }
        $tenant->users()->detach($user->id);

        return $this->ok(['deleted' => true]);
    }

    public function showSettings(): JsonResponse
    {
        return $this->ok(['settings' => $this->tenantSettings(app(TenantContext::class)->get())]);
    }

    public function settings(PlatformMutationRequest $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $data = $request->validated();
        if (isset($data['settings'])) {
            $data['settings'] = array_replace($tenant->settings ?? [], $data['settings']);
        }
        $tenant->update($data);

        return $this->ok(['settings' => $this->tenantSettings($tenant->fresh())]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $page = Tenant::withCount('users')->when($request->string('query')->toString(), fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.addcslashes($value, '%_').'%')->orWhere('slug', 'like', '%'.addcslashes($value, '%_').'%')))->latest('id')->cursorPaginate(30);

        return $this->ok(['items' => collect($page->items())->map(fn ($tenant) => ['id' => $tenant->public_id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'status' => $tenant->status, 'users_count' => $tenant->users_count, 'trial_ends_at' => $tenant->trial_ends_at])], ['next_cursor' => $page->nextCursor()?->encode()]);
    }

    public function audits(): JsonResponse
    {
        return $this->ok(['items' => AuditLog::with(['tenant', 'user'])->latest('id')->limit(100)->get()->map(fn ($log) => ['action' => $log->action, 'tenant' => $log->tenant ? ['id' => $log->tenant->public_id, 'name' => $log->tenant->name] : null, 'actor' => $log->user?->email, 'context' => $log->context, 'ip_address' => $log->ip_address, 'created_at' => $log->created_at?->toIso8601String()])]);
    }

    public function suspend(PlatformMutationRequest $r, Tenant $tenant): JsonResponse
    {
        $d = $r->validated();
        $tenant->update($d);
        $this->audit($r, 'tenant.status_changed', $tenant, $d);

        return $this->ok(['tenant' => $tenant]);
    }

    public function impersonate(Request $r, Tenant $tenant): JsonResponse
    {
        $admin = $tenant->users()->wherePivot('role', 'admin')->firstOrFail();
        $this->audit($r, 'tenant.impersonated', $tenant);
        Auth::guard('web')->login($admin);
        $r->session()->regenerate();
        $r->session()->put('support_impersonation_expires_at', now()->addMinutes(30)->timestamp);

        return $this->ok(['authenticated' => true, 'tenant' => ['id' => $tenant->public_id, 'slug' => $tenant->slug], 'expires_in_minutes' => 30]);
    }

    private function member(User $user): array
    {
        return ['id' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->pivot->role];
    }

    private function tenantSettings(Tenant $tenant): array
    {
        return ['name' => $tenant->name, 'slug' => $tenant->slug, 'default_locale' => $tenant->default_locale, 'currency' => $tenant->currency, 'preferences' => $tenant->settings ?? []];
    }

    private function validationError(string $field, string $message): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'validation', 'field' => $field, 'message' => $message]]], 422);
    }

    private function audit(Request $r, string $action, Tenant $subject, array $context = []): void
    {
        AuditLog::create(['tenant_id' => $subject->id, 'user_id' => $r->user()->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'context' => $context, 'ip_address' => $r->ip(), 'created_at' => now()]);
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }
}
