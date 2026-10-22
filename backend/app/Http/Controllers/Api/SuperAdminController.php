<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\PermissionService;
use App\Services\SubscriptionState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminController extends Controller
{
    public const STATUSES = ['active', 'inactive', 'hold', 'suspended'];

    public const PAYMENT_METHODS = ['cash', 'bank_transfer', 'bkash', 'nagad', 'rocket', 'card', 'other'];

    public function __construct(private SubscriptionState $subscriptions) {}

    public function overview(): JsonResponse
    {
        $now = now();
        $latestIds = DB::table('subscriptions')->selectRaw('MAX(id) as id')->groupBy('tenant_id')->pluck('id');
        $latest = DB::table('subscriptions')->whereIn('id', $latestIds)->get();
        $plans = Plan::all()->keyBy('id');

        $states = ['trialing' => 0, 'active' => 0, 'past_due' => 0, 'cancelled' => 0, 'expired' => 0];
        $mrr = 0;
        $expiring = [];
        $planCounts = [];
        $tenantNames = Tenant::whereIn('id', $latest->pluck('tenant_id'))->get()->keyBy('id');
        foreach ($latest as $sub) {
            $info = $this->subscriptions->describe($sub);
            if (isset($states[$info['state']])) {
                $states[$info['state']]++;
            }
            $plan = $plans->get($sub->plan_id);
            if ($plan && $info['state'] === 'active') {
                $mrr += $this->monthlyPrice($plan);
            }
            if ($plan && in_array($info['state'], ['trialing', 'active', 'past_due'], true)) {
                $planCounts[$plan->code] = ($planCounts[$plan->code] ?? 0) + 1;
                if ($info['ends_at'] && $info['days_left'] !== null && $info['days_left'] <= 7) {
                    $tenant = $tenantNames->get($sub->tenant_id);
                    $expiring[] = ['tenant' => $tenant ? ['id' => $tenant->public_id, 'name' => $tenant->name] : null, 'plan' => $plan->name, 'state' => $info['state'], 'ends_at' => $info['ends_at'], 'days_left' => $info['days_left']];
                }
            }
        }
        usort($expiring, fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        $monthStart = $now->copy()->startOfMonth();
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $from = $monthStart->copy()->subMonths($i);
            $to = $from->copy()->endOfMonth();
            $series[] = [
                'month' => $from->format('Y-m'),
                'revenue_minor' => (int) SubscriptionPayment::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('amount_minor'),
                'new_subscriptions' => DB::table('subscriptions')->whereBetween('created_at', [$from, $to])->count(),
            ];
        }

        return $this->ok([
            'tenants' => [
                'total' => Tenant::count(),
                'by_status' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => Tenant::where('status', $s)->count()]),
                'new_today' => Tenant::where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'new_this_month' => Tenant::where('created_at', '>=', $monthStart)->count(),
            ],
            'subscriptions' => [
                'total_ever' => DB::table('subscriptions')->count(),
                'by_state' => $states,
                'new_today' => DB::table('subscriptions')->where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'new_this_month' => DB::table('subscriptions')->where('created_at', '>=', $monthStart)->count(),
                'expiring_soon' => array_slice($expiring, 0, 8),
                'expiring_soon_count' => count($expiring),
            ],
            'revenue' => [
                'mrr_minor' => $mrr,
                'arr_minor' => $mrr * 12,
                'today_minor' => (int) SubscriptionPayment::where('status', 'paid')->where('paid_at', '>=', $now->copy()->startOfDay())->sum('amount_minor'),
                'this_month_minor' => (int) SubscriptionPayment::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount_minor'),
                'all_time_minor' => (int) SubscriptionPayment::where('status', 'paid')->sum('amount_minor'),
                'currency' => 'BDT',
            ],
            'plans' => collect($planCounts)->map(fn ($count, $code) => ['code' => $code, 'name' => $plans->firstWhere('code', $code)?->name, 'count' => $count])->values(),
            'series' => $series,
            'recent_payments' => SubscriptionPayment::with(['tenant', 'plan'])->latest('paid_at')->limit(8)->get()->map(fn ($p) => $this->payment($p)),
            'generated_at' => $now->toIso8601String(),
        ]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => 'nullable|string|max:80', 'status' => ['nullable', Rule::in(self::STATUSES)], 'state' => 'nullable|in:none,trialing,active,past_due,cancelled,expired', 'per_page' => 'nullable|integer|min:5|max:100']);
        $query = Tenant::withCount('users')
            ->when($data['query'] ?? null, fn ($q, $v) => $q->where(fn ($n) => $n->where('name', 'like', '%'.addcslashes($v, '%_').'%')->orWhere('slug', 'like', '%'.addcslashes($v, '%_').'%')))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id');
        $state = $data['state'] ?? null;
        if ($state) {
            // Subscription state is derived, so filter on the derived value over the matching ids.
            $candidates = $query->clone()->pluck('id');
            $latest = DB::table('subscriptions')->whereIn('id', DB::table('subscriptions')->selectRaw('MAX(id)')->whereIn('tenant_id', $candidates)->groupBy('tenant_id'))->get()->keyBy('tenant_id');
            $ids = $candidates->filter(fn ($id) => $this->subscriptions->describe($latest->get($id))['state'] === $state)->values();
            $query = Tenant::withCount('users')->whereIn('id', $ids)->latest('id');
        }
        $page = $query->paginate($data['per_page'] ?? 20);
        $orders = DB::table('orders')->selectRaw('tenant_id, COUNT(*) as c')->whereIn('tenant_id', collect($page->items())->pluck('id'))->where('created_at', '>=', now()->startOfMonth())->groupBy('tenant_id')->pluck('c', 'tenant_id');

        return $this->ok(
            ['items' => collect($page->items())->map(fn (Tenant $t) => $this->tenantRow($t, (int) ($orders[$t->id] ?? 0)))->values()],
            ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        );
    }

    public function showTenant(Tenant $tenant): JsonResponse
    {
        $orders = (int) DB::table('orders')->where('tenant_id', $tenant->id)->where('created_at', '>=', now()->startOfMonth())->count();
        $limits = ($plan = $this->planOf($tenant)) ? ($plan->feature_limits ?? []) : [];
        unset($limits['stripe_price_id']);

        return $this->ok([
            'tenant' => $this->tenantRow($tenant->loadCount('users'), $orders),
            'members' => $tenant->users()->orderBy('name')->get()->map(fn ($u) => ['name' => $u->name, 'email' => $u->email, 'role' => app(PermissionService::class)->normalize($u->pivot->role)]),
            'payments' => SubscriptionPayment::with('plan')->where('tenant_id', $tenant->id)->latest('paid_at')->limit(20)->get()->map(fn ($p) => $this->payment($p)),
            'limits' => $limits,
            'audits' => AuditLog::with('user')->where('tenant_id', $tenant->id)->latest('id')->limit(15)->get()->map(fn ($l) => $this->audit($l)),
        ]);
    }

    public function setStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'reason' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('status') !== 'active')]]);
        $previous = $tenant->status;
        $tenant->forceFill(['status' => $data['status'], 'status_reason' => $data['status'] === 'active' ? null : $data['reason'], 'status_changed_at' => now(), 'status_changed_by' => $request->user()->id])->save();
        $this->audit($request, 'tenant.status_changed', $tenant, ['from' => $previous, 'to' => $data['status'], 'reason' => $data['reason'] ?? null, 'status' => $data['status']]);

        return $this->ok(['tenant' => $this->tenantRow($tenant->loadCount('users'), 0)]);
    }

    public function assignSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate(['plan_code' => 'required|exists:plans,code', 'status' => 'nullable|in:trialing,active', 'trial_days' => 'nullable|integer|min:1|max:365', 'period_end' => 'nullable|date']);
        $plan = Plan::where('code', $data['plan_code'])->firstOrFail();
        $status = $data['status'] ?? 'active';
        $fields = ['plan_id' => $plan->id, 'status' => $status, 'grace_ends_at' => null, 'ends_at' => null, 'updated_at' => now()];
        if ($status === 'trialing') {
            $fields['trial_ends_at'] = now()->addDays($data['trial_days'] ?? $plan->trial_days ?? 14);
        } else {
            $fields['current_period_end'] = isset($data['period_end']) ? Carbon::parse($data['period_end'])->endOfDay() : null;
        }
        $this->writeSubscription($tenant, $fields, $plan);
        $this->audit($request, 'subscription.assigned', $tenant, ['plan' => $plan->code, 'status' => $status, 'period_end' => $fields['current_period_end'] ?? null]);

        return $this->ok(['tenant' => $this->tenantRow($tenant->loadCount('users'), 0)]);
    }

    public function recordPayment(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'plan_code' => 'nullable|exists:plans,code',
            'amount_minor' => 'required|integer|min:0|max:100000000000',
            'method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'reference' => 'nullable|string|max:120',
            'months' => 'nullable|integer|min:1|max:36',
            'paid_at' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);
        $months = $data['months'] ?? 1;
        $plan = isset($data['plan_code']) ? Plan::where('code', $data['plan_code'])->first() : $this->planOf($tenant);
        $payment = DB::transaction(function () use ($data, $tenant, $plan, $months, $request) {
            $current = DB::table('subscriptions')->where('tenant_id', $tenant->id)->orderByDesc('id')->first();
            $base = $current && $current->current_period_end && Carbon::parse($current->current_period_end)->isFuture() ? Carbon::parse($current->current_period_end) : now();
            $periodEnd = $base->copy()->addMonths($months);
            $sub = $this->writeSubscription($tenant, ['plan_id' => $plan?->id ?? $current?->plan_id, 'status' => 'active', 'current_period_end' => $periodEnd, 'grace_ends_at' => null, 'ends_at' => null, 'updated_at' => now()], $plan);

            return SubscriptionPayment::create(['tenant_id' => $tenant->id, 'subscription_id' => $sub, 'plan_id' => $plan?->id, 'amount_minor' => $data['amount_minor'], 'currency' => $tenant->currency ?: 'BDT', 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'status' => 'paid', 'months' => $months, 'period_start' => $base, 'period_end' => $periodEnd, 'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(), 'recorded_by' => $request->user()->id, 'note' => $data['note'] ?? null]);
        });
        $this->audit($request, 'subscription.payment_recorded', $tenant, ['payment' => $payment->public_id, 'amount_minor' => $payment->amount_minor, 'method' => $payment->method, 'months' => $months]);

        return $this->ok(['payment' => $this->payment($payment->load(['tenant', 'plan'])), 'tenant' => $this->tenantRow($tenant->loadCount('users'), 0)], [], 201);
    }

    public function payments(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'tenant' => 'nullable|string', 'per_page' => 'nullable|integer|min:5|max:100']);
        $page = $this->paymentQuery($data)->with(['tenant', 'plan'])->latest('paid_at')->paginate($data['per_page'] ?? 20);

        return $this->ok(['items' => collect($page->items())->map(fn ($p) => $this->payment($p))->values()], ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'sum_minor' => (int) $this->paymentQuery($data)->sum('amount_minor')]);
    }

    public function plans(): JsonResponse
    {
        $counts = DB::table('subscriptions')->selectRaw('plan_id, COUNT(*) as c')->groupBy('plan_id')->pluck('c', 'plan_id');

        return $this->ok(['items' => Plan::orderBy('sort_order')->orderBy('price_minor')->get()->map(fn ($p) => $this->plan($p) + ['subscriptions_count' => (int) ($counts[$p->id] ?? 0)])]);
    }

    public function savePlan(Request $request, ?Plan $plan = null): JsonResponse
    {
        $data = $request->validate([
            'code' => [$plan ? 'prohibited' : 'required', 'alpha_dash', 'max:40', Rule::unique('plans', 'code')],
            'name' => [$plan ? 'sometimes' : 'required', 'string', 'max:80'],
            'description' => 'nullable|string|max:500',
            'price_minor' => 'sometimes|integer|min:0|max:100000000',
            'currency' => 'sometimes|string|size:3',
            'billing_interval' => 'sometimes|in:month,year',
            'trial_days' => 'sometimes|integer|min:0|max:365',
            'sort_order' => 'sometimes|integer|min:0|max:1000',
            'active' => 'sometimes|boolean',
            'feature_limits' => 'sometimes|array',
            'feature_limits.orders_per_month' => 'nullable|integer|min:0',
            'feature_limits.staff_seats' => 'nullable|integer|min:0',
            'feature_limits.inventory' => 'sometimes|boolean',
            'feature_limits.rentals' => 'sometimes|boolean',
            'feature_limits.bulk_notifications' => 'sometimes|boolean',
            'feature_limits.custom_branding' => 'sometimes|boolean',
        ]);
        if (isset($data['feature_limits'])) {
            // The Stripe price mapping is managed by deployment config, never by this form.
            $data['feature_limits'] = array_merge(['stripe_price_id' => $plan?->feature_limits['stripe_price_id'] ?? null], $data['feature_limits']);
        }
        if ($plan) {
            $plan->update($data);
            $action = 'plan.updated';
        } else {
            $plan = Plan::create($data + ['currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['stripe_price_id' => null], 'active' => true]);
            $action = 'plan.created';
        }
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => Plan::class, 'subject_id' => $plan->id, 'context' => ['code' => $plan->code], 'ip_address' => $request->ip(), 'created_at' => now()]);

        return $this->ok(['plan' => $this->plan($plan)], [], $action === 'plan.created' ? 201 : 200);
    }

    public function audits(Request $request): JsonResponse
    {
        $data = $request->validate(['action' => 'nullable|string|max:80', 'tenant' => 'nullable|string', 'per_page' => 'nullable|integer|min:5|max:100']);
        $page = AuditLog::with(['tenant', 'user'])
            ->when($data['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', addcslashes($v, '%_').'%'))
            ->when($data['tenant'] ?? null, fn ($q, $v) => $q->whereHas('tenant', fn ($t) => $t->where('public_id', $v)))
            ->latest('id')->paginate($data['per_page'] ?? 25);

        return $this->ok(['items' => collect($page->items())->map(fn ($l) => $this->audit($l))->values()], ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate(['type' => 'required|in:subscriptions,payments', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $name = "tailors-{$data['type']}-".now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($data['type'] === 'payments') {
                fputcsv($out, ['Paid at', 'Workshop', 'Slug', 'Plan', 'Amount (BDT)', 'Method', 'Reference', 'Months', 'Period end']);
                $this->paymentQuery($data)->with(['tenant', 'plan'])->orderBy('paid_at')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $p) {
                        fputcsv($out, [$p->paid_at?->toDateTimeString(), $p->tenant?->name, $p->tenant?->slug, $p->plan?->name, number_format($p->amount_minor / 100, 2, '.', ''), $p->method, $p->reference, $p->months, $p->period_end?->toDateString()]);
                    }
                });
            } else {
                fputcsv($out, ['Workshop', 'Slug', 'Status', 'Plan', 'Subscription', 'Ends at', 'Users', 'Registered']);
                Tenant::withCount('users')->orderBy('id')->chunk(200, function ($tenants) use ($out) {
                    foreach ($tenants as $t) {
                        $row = $this->tenantRow($t, 0);
                        fputcsv($out, [$t->name, $t->slug, $t->status, $row['plan']['name'] ?? '', $row['subscription']['state'], $row['subscription']['ends_at'], $t->users_count, $t->created_at?->toDateString()]);
                    }
                });
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function impersonate(Request $request, Tenant $tenant): JsonResponse
    {
        $owner = $tenant->users()->wherePivotIn('role', ['owner', 'admin'])->firstOrFail();
        $this->audit($request, 'tenant.impersonated', $tenant);
        Auth::guard('web')->login($owner);
        $request->session()->regenerate();
        $request->session()->put('support_impersonation_expires_at', now()->addMinutes(30)->timestamp);

        return $this->ok(['authenticated' => true, 'tenant' => ['id' => $tenant->public_id, 'slug' => $tenant->slug], 'expires_in_minutes' => 30]);
    }

    private function paymentQuery(array $data)
    {
        return SubscriptionPayment::query()
            ->when($data['from'] ?? null, fn ($q, $v) => $q->where('paid_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->where('paid_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($data['tenant'] ?? null, fn ($q, $v) => $q->whereHas('tenant', fn ($t) => $t->where('public_id', $v)));
    }

    private function writeSubscription(Tenant $tenant, array $fields, ?Plan $plan): int
    {
        $current = DB::table('subscriptions')->where('tenant_id', $tenant->id)->orderByDesc('id')->first();
        if ($current) {
            DB::table('subscriptions')->where('id', $current->id)->update($fields);

            return $current->id;
        }

        return DB::table('subscriptions')->insertGetId($fields + ['tenant_id' => $tenant->id, 'type' => 'default', 'provider' => 'manual', 'created_at' => now(), 'plan_id' => $plan?->id]);
    }

    private function planOf(Tenant $tenant): ?Plan
    {
        $planId = $this->subscriptions->for($tenant)['plan_id'];

        return $planId ? Plan::find($planId) : null;
    }

    private function monthlyPrice(Plan $plan): int
    {
        return $plan->billing_interval === 'year' ? intdiv($plan->price_minor, 12) : $plan->price_minor;
    }

    private function tenantRow(Tenant $t, int $ordersThisMonth): array
    {
        $info = $this->subscriptions->for($t);
        $plan = $info['plan_id'] ? Plan::find($info['plan_id']) : null;

        return [
            'id' => $t->public_id,
            'name' => $t->name,
            'slug' => $t->slug,
            'status' => $t->status,
            'status_reason' => $t->status_reason,
            'status_changed_at' => $t->status_changed_at?->toIso8601String(),
            'users_count' => $t->users_count ?? null,
            'orders_this_month' => $ordersThisMonth,
            'plan' => $plan ? ['code' => $plan->code, 'name' => $plan->name] : null,
            'subscription' => ['state' => $info['state'], 'ends_at' => $info['ends_at'], 'days_left' => $info['days_left'] ?? null, 'locked' => $info['locked']],
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function payment(SubscriptionPayment $p): array
    {
        return ['id' => $p->public_id, 'tenant' => $p->tenant ? ['id' => $p->tenant->public_id, 'name' => $p->tenant->name, 'slug' => $p->tenant->slug] : null, 'plan' => $p->plan?->name, 'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'method' => $p->method, 'reference' => $p->reference, 'months' => $p->months, 'status' => $p->status, 'period_end' => $p->period_end?->toIso8601String(), 'paid_at' => $p->paid_at?->toIso8601String(), 'note' => $p->note];
    }

    private function plan(Plan $p): array
    {
        $limits = $p->feature_limits ?? [];
        unset($limits['stripe_price_id']);

        return ['code' => $p->code, 'name' => $p->name, 'description' => $p->description, 'price_minor' => $p->price_minor, 'currency' => $p->currency, 'billing_interval' => $p->billing_interval, 'trial_days' => $p->trial_days, 'sort_order' => $p->sort_order, 'active' => $p->active, 'feature_limits' => $limits, 'stripe_connected' => ! empty($p->feature_limits['stripe_price_id'])];
    }

    private function audit(Request|AuditLog $source, ?string $action = null, ?Tenant $subject = null, array $context = []): ?array
    {
        if ($source instanceof AuditLog) {
            return ['id' => (string) $source->id, 'action' => $source->action, 'tenant' => $source->tenant ? ['id' => $source->tenant->public_id, 'name' => $source->tenant->name] : null, 'actor' => $source->user?->email, 'context' => $source->context, 'ip_address' => $source->ip_address, 'created_at' => $source->created_at?->toIso8601String()];
        }
        AuditLog::create(['tenant_id' => $subject->id, 'user_id' => $source->user()->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'context' => $context, 'ip_address' => $source->ip(), 'created_at' => now()]);

        return null;
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }
}
