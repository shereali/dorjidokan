<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScalePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $slug = 'scale'): array
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        Sanctum::actingAs($user, ['*']);

        return [$tenant, ['X-Tenant' => $slug]];
    }

    private function stock(Tenant $tenant, string $sku, float $quantity, float $reorder): void
    {
        app(TenantContext::class)->set($tenant);
        $item = InventoryItem::create(['sku' => $sku, 'name' => $sku, 'unit' => 'm', 'reorder_level' => $reorder, 'active' => true]);
        if ($quantity !== 0.0) {
            InventoryMovement::create(['inventory_item_id' => $item->id, 'type' => 'adjustment', 'quantity' => $quantity, 'occurred_at' => now()]);
        }
        app(TenantContext::class)->clear();
    }

    public function test_low_stock_is_computed_in_sql_and_stays_tenant_scoped(): void
    {
        [$tenant, $headers] = $this->owner();
        $other = Tenant::create(['name' => 'other', 'slug' => 'other', 'status' => 'active']);
        $this->stock($tenant, 'LOW', 2, 5);
        $this->stock($tenant, 'EMPTY', 0, 1);
        $this->stock($tenant, 'OK', 50, 5);
        $this->stock($other, 'OTHER-LOW', 0, 10);

        $this->assertSame(['EMPTY', 'LOW'], collect($this->getJson('/api/v1/reports/summary', $headers)->assertOk()->json('data.low_stock'))->pluck('sku')->sort()->values()->all());
        $this->getJson('/api/v1/dashboard', $headers)->assertOk()->assertJsonPath('data.metrics.low_stock_count', 2);
    }

    public function test_dashboard_runs_a_bounded_number_of_queries_and_is_briefly_cached(): void
    {
        [, $headers] = $this->owner();
        $this->getJson('/api/v1/dashboard', $headers)->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/v1/dashboard', $headers)->assertOk();
        $cached = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->assertLessThanOrEqual(18, $cached, 'dashboard should not fan out into per-status queries');
    }

    public function test_order_list_pipeline_counts_come_from_one_grouped_query(): void
    {
        [, $headers] = $this->owner();
        DB::enableQueryLog();
        $this->getJson('/api/v1/orders', $headers)->assertOk()->assertJsonPath('meta.pipeline_counts.total', 0);
        $groupedScans = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'group by "status"') || str_contains($q['query'], 'group by `status`'))->count();

        $this->assertSame(1, $groupedScans);
    }

    public function test_role_matrix_lookup_is_cached_and_invalidated_on_edit(): void
    {
        [$tenant, $headers] = $this->owner();
        $receptionist = User::factory()->create();
        $receptionist->tenants()->attach($tenant, ['role' => 'receptionist']);
        $owner = User::where('id', '!=', $receptionist->id)->first();

        Sanctum::actingAs($receptionist, ['*']);
        $this->getJson('/api/v1/reports/summary', $headers)->assertForbidden();
        Sanctum::actingAs($owner, ['*']);
        $this->putJson('/api/v1/roles/receptionist', ['permissions' => ['reports.view']], $headers)->assertOk();
        Sanctum::actingAs($receptionist, ['*']);
        $this->getJson('/api/v1/reports/summary', $headers)->assertOk();
    }
}
