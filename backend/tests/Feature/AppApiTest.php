<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Garment;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_dashboard_are_tenant_scoped(): void
    {
        $a = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active']);
        $b = Tenant::create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'owner@example.test', 'password' => bcrypt('Secret123!')]);
        $user->tenants()->attach($a, ['role' => 'admin']);
        app(TenantContext::class)->set($a);
        Customer::create(['name' => 'Alpha Customer', 'mobile_number' => '+8801700000000']);
        app(TenantContext::class)->set($b);
        Customer::create(['name' => 'Beta Customer', 'mobile_number' => '+8801800000000']);
        app(TenantContext::class)->clear();
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/auth/login', ['tenant' => 'alpha', 'email' => 'owner@example.test', 'password' => 'Secret123!'])->assertOk()->assertJsonPath('data.authenticated', true);
        $headers = ['X-Tenant' => 'alpha'];
        $this->getJson('/api/v1/customers', $headers)->assertOk()->assertJsonPath('data.items.0.name', 'Alpha Customer')->assertJsonMissing(['name' => 'Beta Customer']);
        $this->getJson('/api/v1/dashboard', $headers)->assertOk()->assertJsonStructure(['data' => ['metrics' => ['due_today', 'in_progress', 'ready', 'revenue_minor'], 'recent_orders'], 'meta', 'errors']);
        $this->getJson('/api/v1/customers', ['X-Tenant' => 'beta'])->assertNotFound();
    }

    public function test_invalid_login_does_not_issue_token(): void
    {
        $this->postJson('/api/v1/auth/login', ['tenant' => 'missing', 'email' => 'nobody@example.test', 'password' => 'bad'])->assertUnprocessable()->assertJsonPath('errors.0.code', 'invalid_credentials');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_recovery_is_tenant_checked_and_resets_password(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Recovery Shop', 'slug' => 'recovery', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'recover@example.test', 'password' => 'OldPassword123!']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        $this->postJson('/api/v1/auth/forgot-password', ['tenant' => 'recovery', 'email' => $user->email])->assertOk();
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->postJson('/api/v1/auth/reset-password', ['tenant' => 'recovery', 'email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_two_factor_enrollment_and_login_challenge_with_recovery_code(): void
    {
        $tenant = Tenant::create(['name' => 'Secure Shop', 'slug' => 'secure-shop', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'secure@example.test', 'password' => 'VerySecret123!']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        Sanctum::actingAs($user, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => 'secure-shop'];

        $secret = $this->postJson('/api/v1/auth/two-factor/setup', [], $headers)->assertOk()->json('data.secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $recovery = $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => $code], $headers)->assertOk()->json('data.recovery_codes.0');

        $credentials = ['tenant' => 'secure-shop', 'email' => 'secure@example.test', 'password' => 'VerySecret123!'];
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/auth/login', $credentials)->assertUnprocessable()->assertJsonPath('errors.0.code', 'two_factor_required');
        $this->postJson('/api/v1/auth/login', [...$credentials, 'two_factor_code' => $recovery])->assertOk();
        $this->postJson('/api/v1/auth/login', [...$credentials, 'two_factor_code' => $recovery])->assertUnprocessable();
    }

    public function test_staff_role_cannot_change_administrative_catalogs(): void
    {
        $tenant = Tenant::create(['name' => 'Workshop', 'slug' => 'workshop', 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'staff']);
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        $this->postJson('/api/v1/garments', ['name' => 'Sherwani', 'parts' => [['name' => 'Chest', 'unit' => 'inch']]], ['X-Tenant' => $tenant->slug])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'forbidden');
    }

    public function test_tenant_admin_can_manage_garment_catalog_and_workforce_without_internal_ids(): void
    {
        $tenant = Tenant::create(['name' => 'Workshop', 'slug' => 'workshop', 'status' => 'active']);
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        $token = $user->createToken('test', ['app:read', 'app:write'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Tenant' => $tenant->slug];

        $garment = $this->postJson('/api/v1/garments', ['name' => 'Sherwani', 'parts' => [['name' => 'Chest', 'unit' => 'inch'], ['name' => 'Length', 'unit' => 'inch']]], $headers)
            ->assertCreated()->assertJsonPath('data.garment.name', 'Sherwani')->json('data.garment');
        $this->postJson("/api/v1/garments/{$garment['public_id']}/parts", ['name' => 'Collar', 'unit' => 'inch'], $headers)->assertCreated();
        $this->patchJson("/api/v1/garments/{$garment['public_id']}", ['active' => false], $headers)->assertOk()->assertJsonPath('data.garment.active', false);
        $employee = $this->postJson('/api/v1/employees', ['name' => 'Mina', 'mobile_number' => '01812345678', 'employee_type' => 'karigar'], $headers)
            ->assertCreated()->assertJsonMissingPath('data.employee.tenant_id')->json('data.employee');
        $this->patchJson("/api/v1/employees/{$employee['id']}", ['active' => false], $headers)->assertOk()->assertJsonPath('data.employee.active', false);

        app(TenantContext::class)->set($other);
        $foreignGarment = Garment::create(['name' => 'Foreign', 'slug' => 'foreign', 'active' => true]);
        app(TenantContext::class)->clear();
        $this->patchJson("/api/v1/garments/{$foreignGarment->public_id}", ['active' => false], $headers)->assertNotFound();
    }

    public function test_manual_order_lifecycle_records_measurements_assignment_status_and_payments(): void
    {
        $tenant = Tenant::create(['name' => 'Workshop', 'slug' => 'workshop', 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        app(TenantContext::class)->set($tenant);
        $customer = Customer::create(['name' => 'Customer', 'mobile_number' => '+8801711111111']);
        $garment = Garment::create(['name' => 'Panjabi', 'slug' => 'panjabi', 'active' => true]);
        $part = $garment->parts()->create(['name' => 'Chest', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 1, 'required' => true]);
        $employee = Employee::create(['name' => 'Karigar', 'mobile_number' => '+8801811111111', 'employee_type' => 'karigar', 'active' => true]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => $tenant->slug];

        $order = $this->postJson('/api/v1/orders', ['customer_id' => $customer->public_id, 'garment_id' => $garment->public_id, 'total_minor' => 10000, 'paid_minor' => 1000], $headers)->assertCreated()->json('data.order');
        $this->postJson("/api/v1/orders/{$order['id']}/measurements", ['garment_part_id' => $part->public_id, 'value' => 40, 'unit' => 'inch'], $headers)->assertOk();
        $this->postJson("/api/v1/orders/{$order['id']}/assign", ['karigar_id' => $employee->public_id], $headers)->assertOk();
        $this->deleteJson("/api/v1/orders/{$order['id']}", [], $headers)->assertOk()->assertJsonPath('data.archived', true);
        $this->getJson('/api/v1/orders?archived=1', $headers)->assertOk()->assertJsonPath('data.items.0.id', $order['id']);
        $this->postJson("/api/v1/orders/{$order['id']}/restore", [], $headers)->assertOk()->assertJsonPath('data.order.id', $order['id']);
        $this->patchJson("/api/v1/orders/{$order['id']}/status", ['status' => 'ready'], $headers)->assertOk()->assertJsonPath('data.order.status', 'ready');
        $this->postJson("/api/v1/orders/{$order['id']}/payments", ['amount_minor' => 2000, 'method' => 'cash'], $headers)->assertOk()->assertJsonPath('data.order.paid_minor', 3000);
        $this->getJson("/api/v1/customers/{$customer->public_id}/statement", $headers)->assertOk()->assertJsonPath('data.balance_minor', 7000);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame((int) \DB::table('journal_lines')->sum('debit_minor'), (int) \DB::table('journal_lines')->sum('credit_minor'));
        $this->assertDatabaseHas('order_status_events', ['order_id' => Order::where('public_id', $order['id'])->value('id'), 'status' => 'ready', 'actor_type' => 'user']);
    }
}
