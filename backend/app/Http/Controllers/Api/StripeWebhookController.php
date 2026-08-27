<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe webhook endpoint.
 *
 * Cashier verifies the incoming Stripe signature via VerifyWebhookSignature.
 * This controller keeps the app's custom subscription state in sync with
 * Stripe and records dunning/subscription lifecycle events in the audit log.
 */
class StripeWebhookController extends CashierWebhookController
{
    public function __construct()
    {
        $this->middleware(VerifyWebhookSignature::class);
    }

    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $subscription = $this->locateSubscription($payload);
        if (! $subscription) {
            return $this->successMethod();
        }
        $tenant = $subscription->tenant_id ? Tenant::withTrashed()->find($subscription->tenant_id) : null;

        DB::table('subscriptions')
            ->where('id', $subscription->id)
            ->update(['status' => 'past_due', 'grace_ends_at' => now()->addDays(14)]);

        if ($tenant) {
            AuditLog::create(['tenant_id' => $tenant->id, 'user_id' => null, 'action' => 'billing.payment_failed', 'subject_type' => Tenant::class, 'subject_id' => $tenant->id, 'context' => ['subscription' => $subscription->stripe_id, 'invoice' => $payload['data']['object']['id'] ?? null], 'ip_address' => request()->ip(), 'created_at' => now()]);
        }

        return $this->successMethod();
    }

    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $subscription = $this->locateSubscription($payload);
        if (! $subscription) {
            return $this->successMethod();
        }
        $tenant = $subscription->tenant_id ? Tenant::withTrashed()->find($subscription->tenant_id) : null;

        DB::table('subscriptions')->where('id', $subscription->id)->update(['status' => 'cancelled', 'ends_at' => now()]);

        if ($tenant) {
            AuditLog::create(['tenant_id' => $tenant->id, 'user_id' => null, 'action' => 'billing.subscription_cancelled', 'subject_type' => Tenant::class, 'subject_id' => $tenant->id, 'context' => ['subscription' => $subscription->stripe_id], 'ip_address' => request()->ip(), 'created_at' => now()]);
        }

        return $this->successMethod();
    }

    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $subscription = $this->locateSubscription($payload);
        if (! $subscription) {
            return $this->successMethod();
        }
        $object = $payload['data']['object'];
        $status = match ($object['status'] ?? null) {
            'trialing' => 'trialing',
            'active' => 'active',
            'past_due' => 'past_due',
            'unpaid', 'canceled' => 'cancelled',
            default => 'past_due',
        };

        DB::table('subscriptions')->where('id', $subscription->id)->update([
            'status' => $status,
            'stripe_status' => $object['status'] ?? null,
            'ends_at' => ($object['cancel_at_period_end'] ?? false) ? ($object['current_period_end'] ?? null) : null,
            'grace_ends_at' => $status === 'past_due' ? now()->addDays(14) : null,
        ]);

        return $this->successMethod();
    }

    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        return $this->handleCustomerSubscriptionUpdated($payload);
    }

    private function locateSubscription(array $payload): ?Subscription
    {
        $stripeId = $payload['data']['object']['id'] ?? null;
        if (! $stripeId) {
            return null;
        }

        return Subscription::withoutGlobalScopes()->where('stripe_id', $stripeId)->first();
    }
}
