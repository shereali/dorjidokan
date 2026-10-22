<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    private Plan $starter;

    private Plan $growth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->starter = Plan::create(['code' => 'starter', 'name' => 'Starter', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['orders_per_month' => 100], 'active' => true]);
        $this->growth = Plan::create(['code' => 'growth', 'name' => 'Growth', 'price_minor' => 300000, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['orders_per_month' => 2000], 'active' => true]);
        $this->super = User::factory()->create(['is_super_admin' => true, 'two_factor_confirmed_at' => now()]);
        Sanctum::actingAs($this->super, ['*']);
    }

    private function tenant(string $slug, string $role = 'owner'): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => $role]);

        return $tenant;
    }

    public function test_non_super_admins_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->getJson('/api/v1/super-admin/overview')->assertForbidden();
    }

    public function test_status_requires_reason_and_hold_is_read_only_while_suspended_is_blocked(): void
    {
        $tenant = $this->tenant('alpha');
        $owner = $tenant->users()->first();

        $this->patchJson("/api/v1/super-admin/tenants/{$tenant->public_id}/status", ['status' => 'hold'])->assertUnprocessable();
        $this->patchJson("/api/v1/super-admin/tenants/{$tenant->public_id}/status", ['status' => 'hold', 'reason' => 'Payment pending'])
            ->assertOk()->assertJsonPath('data.tenant.status', 'hold');

        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/v1/orders', ['X-Tenant' => 'alpha'])->assertOk();
        $this->postJson('/api/v1/customers', ['name' => 'X', 'mobile_number' => '01700000000'], ['X-Tenant' => 'alpha'])->assertStatus(423)->assertJsonPath('errors.0.code', 'tenant_on_hold');

        Sanctum::actingAs($this->super, ['*']);
        $this->patchJson("/api/v1/super-admin/tenants/{$tenant->public_id}/status", ['status' => 'suspended', 'reason' => 'Abuse'])->assertOk();
        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/v1/orders', ['X-Tenant' => 'alpha'])->assertForbidden()->assertJsonPath('errors.0.code', 'tenant_suspended')->assertJsonPath('errors.0.message', 'Abuse');

        Sanctum::actingAs($this->super, ['*']);
        $this->patchJson("/api/v1/super-admin/tenants/{$tenant->public_id}/status", ['status' => 'active'])->assertOk();
        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/v1/orders', ['X-Tenant' => 'alpha'])->assertOk();
    }

    public function test_manual_payment_activates_and_extends_the_subscription(): void
    {
        $tenant = $this->tenant('beta');
        $url = "/api/v1/super-admin/tenants/{$tenant->public_id}/payments";

        $this->postJson($url, ['plan_code' => 'growth', 'amount_minor' => 300000, 'method' => 'bkash', 'reference' => 'TX123', 'months' => 2])
            ->assertCreated()->assertJsonPath('data.tenant.subscription.state', 'active')->assertJsonPath('data.tenant.plan.code', 'growth');
        $first = DB::table('subscriptions')->where('tenant_id', $tenant->id)->value('current_period_end');

        $this->postJson($url, ['amount_minor' => 300000, 'method' => 'cash', 'months' => 1])->assertCreated();
        $second = DB::table('subscriptions')->where('tenant_id', $tenant->id)->value('current_period_end');
        $this->assertTrue(strtotime($second) > strtotime($first), 'second payment should extend, not reset, the period');
        $this->assertSame(1, DB::table('subscriptions')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'subscription.payment_recorded']);
    }

    public function test_overview_reports_mrr_revenue_and_new_subscriptions(): void
    {
        $a = $this->tenant('one');
        $this->tenant('two');
        $this->postJson("/api/v1/super-admin/tenants/{$a->public_id}/payments", ['plan_code' => 'growth', 'amount_minor' => 300000, 'method' => 'bank_transfer'])->assertCreated();

        $data = $this->getJson('/api/v1/super-admin/overview')->assertOk()->json('data');
        $this->assertSame(2, $data['tenants']['total']);
        $this->assertSame(300000, $data['revenue']['mrr_minor']);
        $this->assertSame(300000, $data['revenue']['today_minor']);
        $this->assertSame(1, $data['subscriptions']['by_state']['active']);
        $this->assertSame(1, $data['subscriptions']['new_today']);
        $this->assertCount(6, $data['series']);
    }

    public function test_expired_subscription_makes_the_workshop_read_only_but_billing_still_works(): void
    {
        $tenant = $this->tenant('gamma');
        DB::table('subscriptions')->insert(['tenant_id' => $tenant->id, 'plan_id' => $this->starter->id, 'type' => 'default', 'provider' => 'manual', 'status' => 'trialing', 'trial_ends_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($tenant->users()->first(), ['*']);

        $this->getJson('/api/v1/orders', ['X-Tenant' => 'gamma'])->assertOk();
        $this->postJson('/api/v1/customers', ['name' => 'X', 'mobile_number' => '01700000000'], ['X-Tenant' => 'gamma'])->assertStatus(402)->assertJsonPath('errors.0.code', 'subscription_expired');
        $this->getJson('/api/v1/billing', ['X-Tenant' => 'gamma'])->assertOk();
    }

    public function test_tenant_list_filters_and_plan_crud(): void
    {
        $this->tenant('delta');
        $e = $this->tenant('epsilon');
        $this->patchJson("/api/v1/super-admin/tenants/{$e->public_id}/status", ['status' => 'inactive', 'reason' => 'Closed shop'])->assertOk();

        $this->getJson('/api/v1/super-admin/tenants?status=inactive')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'epsilon');
        $this->getJson('/api/v1/super-admin/tenants?query=delt')->assertOk()->assertJsonCount(1, 'data.items');

        $this->postJson('/api/v1/super-admin/plans', ['code' => 'pro', 'name' => 'Pro', 'price_minor' => 500000, 'feature_limits' => ['orders_per_month' => 5000, 'staff_seats' => 50, 'inventory' => true]])->assertCreated();
        $this->patchJson('/api/v1/super-admin/plans/pro', ['price_minor' => 450000, 'active' => false])->assertOk()->assertJsonPath('data.plan.price_minor', 450000);
        $this->assertDatabaseHas('plans', ['code' => 'pro', 'active' => 0]);
    }

    public function test_csv_export_lists_payments(): void
    {
        $tenant = $this->tenant('zeta');
        $this->postJson("/api/v1/super-admin/tenants/{$tenant->public_id}/payments", ['plan_code' => 'growth', 'amount_minor' => 250050, 'method' => 'nagad', 'reference' => 'N-9'])->assertCreated();

        $csv = $this->get('/api/v1/super-admin/reports/export?type=payments')->assertOk()->streamedContent();
        $this->assertStringContainsString('Zeta', $csv);
        $this->assertStringContainsString('2500.50', $csv);
    }
}
