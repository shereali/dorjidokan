<?php

namespace App\Services;

use App\Models\Garment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class TenantProvisioner
{
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create(['name' => $data['business_name'], 'slug' => $data['slug'], 'status' => 'active', 'default_locale' => $data['locale'] ?? 'bn', 'currency' => 'BDT']);
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->tenants()->attach($tenant, ['role' => 'admin']);
            app(TenantContext::class)->set($tenant);
            foreach (['panjabi' => ['Panjabi', ['Body Length', 'Chest', 'Waist', 'Hip', 'Shoulder', 'Sleeve', 'Cuff', 'Collar']], 'shirt' => ['Shirt', ['Body Length', 'Chest', 'Waist', 'Shoulder', 'Sleeve', 'Cuff', 'Collar']], 'pant' => ['Pant', ['Outseam', 'Waist', 'Hip', 'Thigh', 'Knee', 'Bottom', 'Inseam']], 'sherwani' => ['Sherwani', ['Body Length', 'Chest', 'Waist', 'Hip', 'Shoulder', 'Sleeve', 'Cuff', 'Collar', 'Front Opening', 'Pocket']]] as $slug => [$name,$parts]) {
                $g = Garment::create(['name' => $name, 'slug' => $slug, 'active' => true]);
                foreach ($parts as $i => $part) {
                    $g->parts()->create(['name' => $part, 'slug' => str($part)->slug(), 'unit' => 'inch', 'display_order' => $i + 1, 'svg_asset_ref' => "/garments/{$slug}/".str($part)->slug().'.svg']);
                }
            }app(TenantContext::class)->clear();
            $plan = Plan::where('code', 'starter')->first();
            if ($plan) {
                $tenant->subscriptions()->create(['plan_id' => $plan->id, 'provider' => 'stripe', 'status' => 'trialing', 'type' => 'default', 'trial_ends_at' => now()->addDays(14)]);
            }

            return [$tenant, $user];
        });
    }
}
