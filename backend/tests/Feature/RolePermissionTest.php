<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant, ['role' => $role]);
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        return $user;
    }

    private function headers(): array
    {
        return ['X-Tenant' => $this->tenant->slug];
    }

    public function test_receptionist_cannot_reach_finance_or_destructive_endpoints(): void
    {
        $this->actingAsRole('receptionist');

        $this->getJson('/api/v1/reports/summary', $this->headers())->assertForbidden()->assertJsonPath('errors.0.code', 'forbidden');
        $this->getJson('/api/v1/expenses', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/payouts', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/billing', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/members', $this->headers())->assertForbidden();
        $this->deleteJson('/api/v1/orders/does-not-matter', [], $this->headers())->assertForbidden();
        $this->getJson('/api/v1/orders', $this->headers())->assertOk();
        $this->getJson('/api/v1/customers', $this->headers())->assertOk();
    }

    public function test_karigar_can_view_orders_but_not_customers_or_money(): void
    {
        $this->actingAsRole('karigar');

        $this->getJson('/api/v1/orders', $this->headers())->assertOk();
        $this->getJson('/api/v1/customers', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/expenses', $this->headers())->assertForbidden();
        $this->postJson('/api/v1/attendance', [], $this->headers())->assertForbidden();
    }

    public function test_accountant_sees_finance_but_cannot_create_orders(): void
    {
        $this->actingAsRole('accountant');

        $this->getJson('/api/v1/reports/summary', $this->headers())->assertOk();
        $this->getJson('/api/v1/accounting/trial-balance', $this->headers())->assertOk();
        $this->postJson('/api/v1/orders', [], $this->headers())->assertForbidden();
    }

    public function test_legacy_role_names_map_to_new_roles(): void
    {
        $this->actingAsRole('admin');
        $this->getJson('/api/v1/members', $this->headers())->assertOk();
        $this->getJson('/api/v1/auth/me', $this->headers())->assertOk()->assertJsonPath('data.role', 'owner');
    }

    public function test_me_returns_the_role_permissions(): void
    {
        $this->actingAsRole('master');

        $permissions = $this->getJson('/api/v1/auth/me', $this->headers())->assertOk()->assertJsonPath('data.role', 'master')->json('data.permissions');
        $this->assertContains('orders.measure', $permissions);
        $this->assertNotContains('reports.view', $permissions);
    }

    public function test_owner_edits_matrix_and_change_takes_effect_immediately(): void
    {
        $owner = $this->actingAsRole('owner');
        $receptionist = User::factory()->create();
        $receptionist->tenants()->attach($this->tenant, ['role' => 'receptionist']);

        $matrix = $this->getJson('/api/v1/roles', $this->headers())->assertOk()->json('data');
        $this->assertCount(6, $matrix['roles']);

        $current = collect($matrix['roles'])->firstWhere('key', 'receptionist')['permissions'];
        $this->putJson('/api/v1/roles/receptionist', ['permissions' => [...$current, 'reports.view', 'billing.manage', 'roles.manage']], $this->headers())
            ->assertOk()
            ->assertJsonMissing(['billing.manage'])
            ->assertJsonMissing(['roles.manage']);

        Sanctum::actingAs($receptionist, ['app:read', 'app:write']);
        $this->getJson('/api/v1/reports/summary', $this->headers())->assertOk();
        $this->putJson('/api/v1/roles/receptionist', ['permissions' => []], $this->headers())->assertForbidden();

        Sanctum::actingAs($owner, ['app:read', 'app:write']);
        $this->deleteJson('/api/v1/roles/receptionist', [], $this->headers())->assertOk();
        Sanctum::actingAs($receptionist, ['app:read', 'app:write']);
        $this->getJson('/api/v1/reports/summary', $this->headers())->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => 'role.permissions_updated']);
    }

    public function test_owner_role_is_locked_and_manager_cannot_manage_roles(): void
    {
        $this->actingAsRole('owner');
        $this->putJson('/api/v1/roles/owner', ['permissions' => []], $this->headers())->assertUnprocessable();

        $this->actingAsRole('manager');
        $this->putJson('/api/v1/roles/karigar', ['permissions' => ['orders.view']], $this->headers())->assertForbidden();
        $this->getJson('/api/v1/members', $this->headers())->assertForbidden();
    }

    public function test_last_owner_cannot_be_demoted_or_removed(): void
    {
        $owner = $this->actingAsRole('owner');
        $other = User::factory()->create();
        $other->tenants()->attach($this->tenant, ['role' => 'owner']);

        // Two owners: demoting the other one is fine, then they are the only owner.
        $this->patchJson("/api/v1/members/{$other->public_id}", ['role' => 'manager'], $this->headers())->assertOk();
        $other->tenants()->updateExistingPivot($this->tenant->id, ['role' => 'owner']);
        $this->deleteJson("/api/v1/members/{$other->public_id}", [], $this->headers())->assertOk();

        $this->patchJson("/api/v1/members/{$owner->public_id}", ['role' => 'manager'], $this->headers())->assertUnprocessable();
    }
}
