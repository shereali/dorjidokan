<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        $tenant = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        $plan = Plan::create(['code' => 'operations', 'name' => 'Operations', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['inventory' => true, 'rentals' => true], 'active' => true]);
        DB::table('subscriptions')->insert(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'provider' => 'stripe', 'status' => 'active', 'type' => 'default', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        return [$tenant, $user, ['X-Tenant' => 'alpha']];
    }

    public function test_purchase_adds_stock_and_sale_consumes_it_atomically(): void
    {
        [$tenant,$user,$h] = $this->auth();
        $item = $this->postJson('/api/v1/inventory', ['sku' => 'FAB-001', 'name' => 'Cotton', 'unit' => 'yard', 'reorder_level' => 5], $h)->assertCreated()->json('data.item');
        $this->postJson('/api/v1/purchases', ['items' => [['inventory_item_id' => $item['public_id'], 'quantity' => 10, 'unit_cost_minor' => 25000]]], $h)->assertCreated()->assertJsonPath('data.purchase.total_minor', 250000);
        $this->getJson('/api/v1/inventory', $h)->assertOk()->assertJsonPath('data.items.0.movements_sum_quantity', 10);
        $this->postJson('/api/v1/sales', ['items' => [['inventory_item_id' => $item['public_id'], 'quantity' => 3, 'unit_price_minor' => 40000]], 'paid_minor' => 120000, 'payment_method' => 'cash'], $h)->assertCreated()->assertJsonPath('data.sale.total_minor', 120000);
        $this->getJson('/api/v1/inventory', $h)->assertJsonPath('data.items.0.movements_sum_quantity', 7);
        $this->postJson('/api/v1/sales', ['items' => [['inventory_item_id' => $item['public_id'], 'quantity' => 8, 'unit_price_minor' => 40000]]], $h)->assertUnprocessable();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_cross_tenant_route_binding_cannot_adjust_inventory(): void
    {
        [$tenant,$user,$h] = $this->auth();
        $other = Tenant::create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active']);
        app(TenantContext::class)->set($other);
        $item = InventoryItem::create(['sku' => 'SECRET', 'name' => 'Other stock', 'unit' => 'piece']);
        app(TenantContext::class)->clear();
        $this->postJson("/api/v1/inventory/{$item->public_id}/adjust", ['quantity' => 1, 'reason' => 'attempt'], $h)->assertNotFound();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_plan_feature_denial_uses_consistent_upgrade_envelope(): void
    {
        [$tenant, $user, $headers] = $this->auth();
        DB::table('subscriptions')->where('tenant_id', $tenant->id)->delete();

        $this->getJson('/api/v1/inventory', $headers)->assertForbidden()
            ->assertJsonPath('meta.upgrade_required', true)
            ->assertJsonPath('errors.0.code', 'feature_unavailable');
    }

    public function test_piece_work_is_paid_once_in_an_auditable_batch(): void
    {
        [$tenant, $user, $headers] = $this->auth();
        app(TenantContext::class)->set($tenant);
        $employee = Employee::create(['name' => 'Karigar', 'employee_type' => 'karigar', 'active' => true]);
        app(TenantContext::class)->clear();
        $this->postJson('/api/v1/work-entries', ['employee_id' => $employee->public_id, 'work_type' => 'Stitching', 'quantity' => 2, 'rate_minor' => 5000], $headers)->assertCreated();
        $payload = ['employee_id' => $employee->public_id, 'period_from' => now()->toDateString(), 'period_to' => now()->toDateString(), 'payment_method' => 'cash'];
        $this->postJson('/api/v1/payouts', $payload, $headers)->assertCreated()->assertJsonPath('data.payout.total_minor', 10000)->assertJsonCount(1, 'data.payout.items');
        $this->postJson('/api/v1/payouts', $payload, $headers)->assertUnprocessable();
        $this->assertDatabaseHas('work_entries', ['employee_id' => $employee->id, 'status' => 'paid']);
        $this->assertDatabaseCount('payout_batches', 1);
    }

    public function test_rental_return_records_condition_and_settles_deposit_against_damage(): void
    {
        [,,$headers] = $this->auth();
        $customer = $this->postJson('/api/v1/customers', ['name' => 'Rental Client', 'mobile_number' => '01700000000'], $headers)->assertCreated()->json('data.customer');
        $item = $this->postJson('/api/v1/inventory', ['sku' => 'RNT-001', 'name' => 'Sherwani', 'unit' => 'piece'], $headers)->assertCreated()->json('data.item');
        $this->postJson("/api/v1/inventory/{$item['public_id']}/adjust", ['quantity' => 1, 'reason' => 'Opening rental stock'], $headers)->assertCreated();
        $rental = $this->postJson('/api/v1/rentals', ['customer_id' => $customer['id'], 'starts_on' => now()->toDateString(), 'due_on' => now()->addDays(2)->toDateString(), 'rent_minor' => 30000, 'deposit_minor' => 10000, 'items' => [['inventory_item_id' => $item['public_id'], 'quantity' => 1, 'condition_out' => 'New']]], $headers)->assertCreated()->json('data.rental');

        $this->postJson("/api/v1/rentals/{$rental['id']}/return", ['conditions' => [$item['public_id'] => 'Torn cuff'], 'damage_charge_minor' => 15000, 'settlement_note' => 'Customer owes the balance'], $headers)
            ->assertOk()->assertJsonPath('data.rental.status', 'returned')->assertJsonPath('data.rental.deposit_refunded_minor', 0)->assertJsonPath('data.rental.settlement_due_minor', 5000)->assertJsonPath('data.rental.items.0.condition_in', 'Torn cuff');
        $this->getJson('/api/v1/inventory', $headers)->assertJsonPath('data.items.0.movements_sum_quantity', 1);
    }

    public function test_expense_receipt_is_private_and_approval_posts_balanced_ledger(): void
    {
        [$tenant, $user, $headers] = $this->auth();
        Storage::fake('local');
        $expense = $this->postJson('/api/v1/expenses', ['category' => 'Electricity', 'amount_minor' => 25000, 'expense_date' => now()->toDateString(), 'note' => 'Monthly bill'], $headers)
            ->assertCreated()->assertJsonPath('data.expense.status', 'pending')->json('data.expense');
        $attachment = $this->post('/api/v1/expenses/'.$expense['id'].'/attachments', ['attachment' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf')], $headers)
            ->assertCreated()->json('data.attachment');
        $this->get('/api/v1/expense-attachments/'.$attachment['id'], $headers)->assertOk()->assertDownload('bill.pdf');
        $this->postJson('/api/v1/expenses/'.$expense['id'].'/approve', [], $headers)->assertOk()->assertJsonPath('data.expense.status', 'approved');
        $this->postJson('/api/v1/expenses/'.$expense['id'].'/approve', [], $headers)->assertOk();
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame((int) DB::table('journal_lines')->sum('debit_minor'), (int) DB::table('journal_lines')->sum('credit_minor'));
    }
}
