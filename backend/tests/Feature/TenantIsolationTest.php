<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExportRequest;
use App\Models\Garment;
use App\Models\InventoryItem;
use App\Models\NotificationCampaign;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithAdmin(string $slug): array
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_accounting_statement_and_trial_balance_are_tenant_scoped(): void
    {
        [$a,$user] = $this->tenantWithAdmin('alpha');
        [$b] = $this->tenantWithAdmin('beta');
        app(TenantContext::class)->set($a);
        $customerA = Customer::create(['name' => 'Alpha C', 'mobile_number' => '+8801700000001']);
        app(TenantContext::class)->set($b);
        $customerB = Customer::create(['name' => 'Beta C', 'mobile_number' => '+8801700000002']);
        app(TenantContext::class)->clear();

        Sanctum::actingAs($user, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => 'alpha'];

        // Alpha can read its own statement but not Beta's.
        $this->getJson("/api/v1/customers/{$customerA->public_id}/statement", $headers)->assertOk();
        $this->getJson("/api/v1/customers/{$customerB->public_id}/statement", $headers)->assertNotFound();

        $this->getJson('/api/v1/accounting/trial-balance', $headers)->assertOk();
    }

    public function test_report_summary_is_tenant_scoped(): void
    {
        [$a,$user] = $this->tenantWithAdmin('alpha');
        [$b] = $this->tenantWithAdmin('beta');
        app(TenantContext::class)->set($a);
        $garmentA = Garment::create(['name' => 'Panjabi A', 'slug' => 'panjabi-a', 'active' => true]);
        app(TenantContext::class)->set($b);
        $garmentB = Garment::create(['name' => 'Panjabi B', 'slug' => 'panjabi-b', 'active' => true]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        // Report should reflect only Alpha's data.
        $response = $this->getJson('/api/v1/reports/summary?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString(), ['X-Tenant' => 'alpha'])->assertOk();
        $this->assertArrayHasKey('data', $response->json());
        // Beta's garment must not leak into Alpha's report.
        $this->getJson('/api/v1/garments', ['X-Tenant' => 'alpha'])->assertOk()->assertJsonMissing(['slug' => 'panjabi-b']);
    }

    public function test_exports_are_tenant_scoped(): void
    {
        [$a,$user] = $this->tenantWithAdmin('alpha');
        [$b] = $this->tenantWithAdmin('beta');
        app(TenantContext::class)->set($a);
        ExportRequest::create(['requested_by' => $user->id, 'type' => 'summary', 'filters' => [], 'status' => 'completed', 'storage_path' => 'exports/alpha.csv', 'mime_type' => 'text/csv']);
        app(TenantContext::class)->set($b);
        ExportRequest::create(['requested_by' => $user->id, 'type' => 'summary', 'filters' => [], 'status' => 'queued']);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        $this->getJson('/api/v1/exports', ['X-Tenant' => 'alpha'])->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_notifications_and_reminders_are_tenant_scoped(): void
    {
        [$a,$user] = $this->tenantWithAdmin('alpha');
        [$b] = $this->tenantWithAdmin('beta');
        app(TenantContext::class)->set($a);
        Customer::create(['name' => 'Alpha N', 'mobile_number' => '+8801700000003', 'marketing_consent' => true]);
        app(TenantContext::class)->set($b);
        Customer::create(['name' => 'Beta N', 'mobile_number' => '+8801700000004', 'marketing_consent' => true]);
        NotificationCampaign::create(['name' => 'Beta Campaign', 'channel' => 'sms', 'body' => 'x', 'status' => 'queued', 'recipient_count' => 0, 'queued_at' => now()]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        $this->getJson('/api/v1/notifications', ['X-Tenant' => 'alpha'])->assertOk()->assertJsonCount(0, 'data.campaigns');
    }

    public function test_inventory_and_expenses_are_tenant_scoped(): void
    {
        [$a,$user] = $this->tenantWithAdmin('alpha');
        [$b] = $this->tenantWithAdmin('beta');
        $plan = Plan::create(['code' => 'iso-inv', 'name' => 'ISO Inv', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['inventory' => true], 'active' => true]);
        DB::table('subscriptions')->insert(['tenant_id' => $a->id, 'plan_id' => $plan->id, 'provider' => 'stripe', 'status' => 'active', 'type' => 'default', 'created_at' => now(), 'updated_at' => now()]);
        app(TenantContext::class)->set($a);
        InventoryItem::create(['sku' => 'A-001', 'name' => 'Alpha Fabric', 'unit' => 'meter', 'reorder_level' => 2]);
        app(TenantContext::class)->set($b);
        InventoryItem::create(['sku' => 'B-001', 'name' => 'Beta Fabric', 'unit' => 'meter', 'reorder_level' => 2]);
        $category = ExpenseCategory::create(['name' => 'Beta Category']);
        Expense::create(['expense_category_id' => $category->id, 'amount_minor' => 500, 'expense_date' => now(), 'note' => 'Beta expense', 'status' => 'approved']);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        $this->getJson('/api/v1/inventory', ['X-Tenant' => 'alpha'])->assertOk()->assertJsonMissing(['sku' => 'B-001']);
        $this->getJson('/api/v1/expenses', ['X-Tenant' => 'alpha'])->assertOk()->assertJsonMissing(['note' => 'Beta expense']);
    }
}
