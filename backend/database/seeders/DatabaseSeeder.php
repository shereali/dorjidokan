<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Garment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $starterPrice = env('STRIPE_PRICE_STARTER');
        $growthPrice = env('STRIPE_PRICE_GROWTH');
        $plan = Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['orders_per_month' => 100, 'staff_seats' => 3, 'inventory' => true, 'rentals' => true, 'bulk_notifications' => false, 'custom_branding' => false, 'stripe_price_id' => $starterPrice], 'active' => true]);
        Plan::firstOrCreate(['code' => 'growth'], ['name' => 'Growth', 'price_minor' => 299900, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['orders_per_month' => 1000, 'staff_seats' => 20, 'inventory' => true, 'rentals' => true, 'bulk_notifications' => true, 'custom_branding' => true, 'stripe_price_id' => $growthPrice], 'active' => true]);

        $tenant = Tenant::firstOrCreate(['slug' => 'heritage-tailors'], ['name' => 'Heritage Tailors', 'status' => 'active', 'default_locale' => 'bn', 'currency' => 'BDT']);
        $user = User::firstOrCreate(['email' => 'admin@tailors.test'], ['name' => 'Tenant Admin', 'password' => bcrypt('ChangeMe123!')]);
        $user->tenants()->syncWithoutDetaching([$tenant->id => ['role' => 'admin']]);
        app(TenantContext::class)->set($tenant);
        Employee::firstOrCreate(['mobile_number' => '+8801700000001'], ['name' => 'Rahim Karigar', 'employee_type' => 'karigar', 'active' => true]);
        foreach (['panjabi' => ['Panjabi', ['Body Length', 'Chest', 'Waist', 'Hip', 'Shoulder', 'Sleeve', 'Cuff', 'Collar']], 'shirt' => ['Shirt', ['Body Length', 'Chest', 'Waist', 'Shoulder', 'Sleeve', 'Cuff', 'Collar']], 'pant' => ['Pant', ['Outseam', 'Waist', 'Hip', 'Thigh', 'Knee', 'Bottom', 'Inseam']], 'sherwani' => ['Sherwani', ['Body Length', 'Chest', 'Waist', 'Hip', 'Shoulder', 'Sleeve', 'Cuff', 'Collar', 'Front Opening', 'Pocket']]] as $slug => [$name,$parts]) {
            $g = Garment::firstOrCreate(['slug' => $slug], ['name' => $name, 'active' => true]);
            foreach ($parts as $i => $part) {
                $g->parts()->firstOrCreate(['slug' => str($part)->slug()], ['name' => $part, 'unit' => 'inch', 'display_order' => $i + 1, 'svg_asset_ref' => "/garments/{$slug}/".str($part)->slug().'.svg', 'required' => true]);
            }
        }
        app(TenantContext::class)->clear();
        if (! DB::table('subscriptions')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('subscriptions')->insert(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'provider' => 'stripe', 'status' => 'trialing', 'type' => 'default', 'trial_ends_at' => now()->addDays(14), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
