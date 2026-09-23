<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $starterPrice = env('STRIPE_PRICE_STARTER');
        $growthPrice = env('STRIPE_PRICE_GROWTH');

        Plan::firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'price_minor' => 0,
                'currency' => 'BDT',
                'billing_interval' => 'month',
                'feature_limits' => [
                    'orders_per_month' => 100,
                    'staff_seats' => 5,
                    'inventory' => true,
                    'rentals' => true,
                    'bulk_notifications' => true,
                    'custom_branding' => true,
                    'stripe_price_id' => $starterPrice,
                ],
                'active' => true,
            ]
        );

        Plan::firstOrCreate(
            ['code' => 'growth'],
            [
                'name' => 'Growth Atelier',
                'price_minor' => 299900,
                'currency' => 'BDT',
                'billing_interval' => 'month',
                'feature_limits' => [
                    'orders_per_month' => 2000,
                    'staff_seats' => 25,
                    'inventory' => true,
                    'rentals' => true,
                    'bulk_notifications' => true,
                    'custom_branding' => true,
                    'stripe_price_id' => $growthPrice,
                ],
                'active' => true,
            ]
        );
    }
}
