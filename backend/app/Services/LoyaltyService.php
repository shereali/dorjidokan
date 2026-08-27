<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loyalty/reference points program (legacy point_tbl).
 *
 * Tenants configure how many points a customer earns per 100 currency units
 * spent (default 1). Points are accrued on order invoices and redeemed as
 * payment credit. Balances are derived from append-only loyalty_points rows
 * and cached on the account for fast reads.
 */
class LoyaltyService
{
    public const POINTS_PER_CURRENCY_UNIT = 100;

    public function pointsPerSpend(?TenantContext $context = null): int
    {
        $tenant = ($context ?? app(TenantContext::class))->get();

        return (int) ($tenant->loyalty_settings['points_per_100'] ?? 1);
    }

    public function pointsToCurrencyMinor(int $points): int
    {
        // 1 point = 1 currency unit (e.g. ৳1 = 100 minor/paisa).
        return $points * 100;
    }

    public function account(Customer $customer): LoyaltyAccount
    {
        return LoyaltyAccount::firstOrCreate(['customer_id' => $customer->id], ['balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0]);
    }

    public function earnForOrder(Order $order, ?int $actorId = null): void
    {
        $tenant = app(TenantContext::class)->get();
        if (($tenant->loyalty_settings['enabled'] ?? true) === false) {
            return;
        }
        // total_minor is in minor units; 100 minor = 1 currency unit.
        // points_per_100 = points earned per 100 currency units spent.
        $points = (int) floor(($order->total_minor ?? 0) * max(1, $this->pointsPerSpend()) / 10000);
        if ($points <= 0) {
            return;
        }
        $this->earn($order->customer, $points, 'Order '.$order->order_number, $order, $actorId);
    }

    public function earn(Customer $customer, int $points, string $reason, ?Order $order = null, ?int $actorId = null): LoyaltyPoint
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => 'Points must be positive.']);
        }

        return DB::transaction(function () use ($customer, $points, $reason, $order, $actorId) {
            $account = $this->account($customer);
            $account->increment('balance', $points);
            $account->increment('total_earned', $points);

            return LoyaltyPoint::create(['loyalty_account_id' => $account->id, 'customer_id' => $customer->id, 'order_id' => $order?->id, 'type' => 'earn', 'points' => $points, 'reason' => $reason, 'created_by' => $actorId]);
        });
    }

    public function redeem(Customer $customer, int $points, string $reason, ?Order $order = null, ?int $actorId = null): LoyaltyPoint
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => 'Points must be positive.']);
        }
        $account = $this->account($customer);
        if ($account->balance < $points) {
            throw ValidationException::withMessages(['points' => 'Insufficient loyalty balance.']);
        }

        return DB::transaction(function () use ($customer, $account, $points, $reason, $order, $actorId) {
            $account->decrement('balance', $points);
            $account->increment('total_redeemed', $points);

            return LoyaltyPoint::create(['loyalty_account_id' => $account->id, 'customer_id' => $customer->id, 'order_id' => $order?->id, 'type' => 'redeem', 'points' => -$points, 'reason' => $reason, 'created_by' => $actorId]);
        });
    }
}
