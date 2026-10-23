<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Garment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithOrders(string $slug, string $role = 'owner'): array
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => $role]);
        app(TenantContext::class)->set($tenant);
        $customer = Customer::create(['name' => "$slug customer", 'mobile_number' => '+88017'.random_int(10000000, 99999999)]);
        $garment = Garment::create(['name' => 'Panjabi', 'slug' => "panjabi-$slug", 'active' => true]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['*']);

        return [$tenant, $customer, $garment, ['X-Tenant' => $slug]];
    }

    public function test_customer_list_carries_order_count_due_and_last_order(): void
    {
        [, $customer, $garment, $h] = $this->tenantWithOrders('alpha');
        $this->postJson('/api/v1/orders', ['customer_id' => $customer->public_id, 'garment_id' => $garment->public_id, 'total_minor' => 10000, 'paid_minor' => 4000], $h)->assertCreated();
        $second = $this->postJson('/api/v1/orders', ['customer_id' => $customer->public_id, 'garment_id' => $garment->public_id, 'total_minor' => 5000, 'paid_minor' => 5000], $h)->assertCreated()->json('data.order');
        $cancelled = $this->postJson('/api/v1/orders', ['customer_id' => $customer->public_id, 'garment_id' => $garment->public_id, 'total_minor' => 9000, 'paid_minor' => 0], $h)->assertCreated()->json('data.order');
        $this->patchJson("/api/v1/orders/{$cancelled['id']}/status", ['status' => 'cancelled'], $h)->assertOk();

        $row = $this->getJson('/api/v1/customers', $h)->assertOk()->json('data.items.0');

        $this->assertSame(3, $row['orders_count']);
        $this->assertSame(6000, $row['due_minor'], 'due ignores cancelled orders');
        $this->assertNotNull($row['last_order_at']);
        $this->assertNotNull($second);
    }

    public function test_customer_without_orders_reports_zeroes(): void
    {
        [, , , $h] = $this->tenantWithOrders('alpha');

        $row = $this->getJson('/api/v1/customers', $h)->assertOk()->json('data.items.0');

        $this->assertSame(0, $row['orders_count']);
        $this->assertSame(0, $row['due_minor']);
        $this->assertNull($row['last_order_at']);
    }

    public function test_order_history_lists_only_that_customers_orders_newest_first(): void
    {
        [, $customer, $garment, $h] = $this->tenantWithOrders('alpha');
        $first = $this->postJson('/api/v1/orders', ['customer_id' => $customer->public_id, 'garment_id' => $garment->public_id, 'total_minor' => 1000, 'paid_minor' => 0], $h)->assertCreated()->json('data.order');
        $last = $this->postJson('/api/v1/orders', ['customer_id' => $customer->public_id, 'garment_id' => $garment->public_id, 'total_minor' => 2000, 'paid_minor' => 500], $h)->assertCreated()->json('data.order');

        $items = $this->getJson("/api/v1/customers/{$customer->public_id}/orders", $h)->assertOk()->json('data.items');

        $this->assertSame([$last['id'], $first['id']], array_column($items, 'id'));
        $this->assertSame('Panjabi', $items[0]['garment']);
        $this->assertSame(2000, $items[0]['total_minor']);
    }

    public function test_order_history_does_not_cross_tenants(): void
    {
        [, $alphaCustomer] = $this->tenantWithOrders('alpha');
        [, , , $betaHeaders] = $this->tenantWithOrders('beta');

        $this->getJson("/api/v1/customers/{$alphaCustomer->public_id}/orders", $betaHeaders)->assertNotFound();
    }

    public function test_order_history_needs_customer_and_order_access(): void
    {
        [, $customer, , $h] = $this->tenantWithOrders('alpha', 'karigar');

        $this->getJson("/api/v1/customers/{$customer->public_id}/orders", $h)->assertForbidden();
    }
}
