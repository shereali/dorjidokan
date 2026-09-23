<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'heritage-tailors'],
            [
                'name' => 'Heritage Tailors Atelier',
                'status' => 'active',
                'default_locale' => 'bn',
                'currency' => 'BDT',
                'settings' => [
                    'order_prefix' => 'HT',
                    'tax_rate_percent' => 5,
                    'invoice_footer' => 'Thank you for choosing Heritage Tailors. Fitting guaranteed for 30 days.',
                    'sms_sender_id' => 'HERITAGE',
                ],
                'loyalty_settings' => [
                    'points_per_100_taka' => 1,
                    'point_redeem_value_taka' => 1,
                ],
            ]
        );

        $plan = Plan::where('code', 'starter')->first();
        if ($plan && ! $tenant->subscriptions()->exists()) {
            $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'provider' => 'stripe',
                'status' => 'trialing',
                'type' => 'default',
                'trial_ends_at' => now()->addDays(14),
            ]);
        }
    }
}
