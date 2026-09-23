<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\UsageCounter;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

class FeatureGateService
{
    public function limits(): array
    {
        $tenant = app(TenantContext::class)->get();
        if (! $tenant) {
            return [];
        }

        $subscription = $tenant->subscriptions()
            ->where(fn ($query) => $query->whereIn('stripe_status', ['active', 'trialing'])->orWhereIn('status', ['active', 'trialing']))
            ->latest('id')->first();

        if ($subscription) {
            return Plan::find($subscription->plan_id)?->feature_limits ?? [];
        }

        return Plan::where('code', 'starter')->first()?->feature_limits ?? [];
    }

    public function allows(string $feature): bool
    {
        $value = $this->limits()[$feature] ?? false;

        return $value === true || (is_numeric($value) && $value > 0);
    }

    public function limit(string $metric): ?int
    {
        $value = $this->limits()[$metric] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function usage(string $metric): int
    {
        return match ($metric) {
            'orders_per_month' => Order::where('created_at', '>=', now()->startOfMonth())->count(),
            'staff_seats' => app(TenantContext::class)->get()->users()->count(),
            default => (int) (UsageCounter::where('metric', $metric)->where('period', now()->format('Y-m'))->value('value') ?? 0),
        };
    }

    public function hasCapacity(string $metric): bool
    {
        $limit = $this->limit($metric);

        return $limit === null || $this->usage($metric) < $limit;
    }

    public function denial(string $code, string $message): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => ['upgrade_required' => true], 'errors' => [['code' => $code, 'message' => $message]]], 403);
    }
}
