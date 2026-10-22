<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Derives what a tenant's latest subscription means right now.
 *
 * state: none | trialing | active | past_due | cancelled | expired
 * An "expired" subscription (trial over, paid period over, grace over) locks the
 * workshop to read-only until it is renewed; billing routes stay usable.
 */
class SubscriptionState
{
    public function for(Tenant $tenant): array
    {
        $sub = DB::table('subscriptions')->where('tenant_id', $tenant->id)->orderByDesc('id')->first();

        return $this->describe($sub);
    }

    public function describe(?object $sub): array
    {
        if (! $sub) {
            return ['state' => 'none', 'ends_at' => null, 'locked' => false, 'plan_id' => null, 'subscription_id' => null];
        }
        $status = in_array($sub->stripe_status, ['active', 'trialing'], true) ? $sub->stripe_status : $sub->status;
        $end = match ($status) {
            'trialing' => $sub->trial_ends_at,
            'active' => $sub->current_period_end,
            'past_due' => $sub->grace_ends_at,
            default => $sub->ends_at ?? now(),
        };
        $endAt = $end ? now()->parse($end) : null;
        $state = $status;
        if (in_array($status, ['cancelled', 'canceled'], true)) {
            $state = $endAt && $endAt->isFuture() ? 'cancelled' : 'expired';
        } elseif ($endAt && $endAt->isPast()) {
            $state = 'expired';
        }

        return [
            'state' => $state,
            'ends_at' => $endAt?->toIso8601String(),
            'days_left' => $endAt instanceof CarbonInterface ? (int) now()->diffInDays($endAt, false) : null,
            'locked' => $state === 'expired',
            'plan_id' => $sub->plan_id,
            'subscription_id' => $sub->id,
        ];
    }
}
