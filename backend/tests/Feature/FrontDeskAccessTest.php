<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Front-desk roles take orders and sell fabric, but must not reach the catalogue, stock or back-office pages.
 * They still read the two lists those jobs need (garments to take an order, items for the POS).
 */
class FrontDeskAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $plan = Plan::create(['code' => 'operations', 'name' => 'Operations', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['inventory' => true, 'rentals' => true], 'active' => true]);
        DB::table('subscriptions')->insert(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id, 'provider' => 'stripe', 'status' => 'active', 'type' => 'default', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function as(string $role): array
    {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant, ['role' => $role]);
        Sanctum::actingAs($user, ['*']);

        return ['X-Tenant' => $this->tenant->slug];
    }

    private function permissions(string $role): array
    {
        $headers = $this->as($role);

        return $this->getJson('/api/v1/auth/me', $headers)->assertOk()->json('data.permissions');
    }

    public function test_master_and_receptionist_have_no_stock_or_back_office_pages(): void
    {
        foreach (['master', 'receptionist'] as $role) {
            $permissions = $this->permissions($role);
            foreach (['garments.manage', 'inventory.view', 'inventory.manage', 'attendance.manage', 'work.manage', 'payouts.view', 'expenses.view', 'purchases.manage'] as $denied) {
                $this->assertNotContains($denied, $permissions, "$role must not hold $denied");
            }
        }
    }

    public function test_receptionist_has_no_garment_page_but_master_keeps_it(): void
    {
        $this->assertNotContains('garments.view', $this->permissions('receptionist'));
        $this->assertContains('garments.view', $this->permissions('master'));
    }

    public function test_front_desk_still_reads_the_lists_their_work_needs(): void
    {
        $headers = $this->as('receptionist');
        $this->getJson('/api/v1/garments', $headers)->assertOk();
        $this->getJson('/api/v1/inventory', $headers)->assertOk();

        $headers = $this->as('master');
        $this->getJson('/api/v1/garments', $headers)->assertOk();
    }

    public function test_front_desk_cannot_change_the_catalogue_or_stock(): void
    {
        $headers = $this->as('receptionist');
        $this->postJson('/api/v1/garments', ['name' => 'Hack'], $headers)->assertForbidden();
        $this->postJson('/api/v1/inventory', ['sku' => 'X', 'name' => 'X'], $headers)->assertForbidden();
        $this->postJson('/api/v1/purchases', [], $headers)->assertForbidden();
        $this->getJson('/api/v1/suppliers', $headers)->assertForbidden();
    }

    public function test_karigar_sees_only_orders_and_garments_read_only(): void
    {
        $permissions = $this->permissions('karigar');

        foreach (['customers.view', 'employees.view', 'inventory.view', 'sales.manage', 'rentals.manage', 'orders.create'] as $denied) {
            $this->assertNotContains($denied, $permissions);
        }
        $headers = $this->as('karigar');
        $this->getJson('/api/v1/customers', $headers)->assertForbidden();
        $this->getJson('/api/v1/inventory', $headers)->assertForbidden();
        $this->getJson('/api/v1/employees', $headers)->assertForbidden();
    }
}
