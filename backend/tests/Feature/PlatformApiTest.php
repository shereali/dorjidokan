<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\StripeWebhookController;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_creates_usable_tenant_catalog_and_trial(): void
    {
        Plan::create(['code' => 'starter', 'name' => 'Starter', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['orders_per_month' => 100], 'active' => true]);
        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/onboarding', ['business_name' => 'Needle House', 'slug' => 'needle-house', 'name' => 'Owner', 'email' => 'owner@needle.test', 'password' => 'VerySecret123!', 'password_confirmation' => 'VerySecret123!', 'locale' => 'bn'])->assertCreated();
        $this->assertDatabaseHas('tenants', ['slug' => 'needle-house']);
        $tenant = Tenant::where('public_id', $response->json('data.tenant.id'))->firstOrFail();
        $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $tenant->id, 'role' => 'admin']);
        $this->assertDatabaseCount('garments', 4);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'status' => 'trialing']);
    }

    public function test_super_admin_requires_two_factor_and_actions_are_audited(): void
    {
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $owner = User::factory()->create();
        $owner->tenants()->attach($tenant, ['role' => 'admin']);
        $super = User::factory()->create(['is_super_admin' => true]);
        Sanctum::actingAs($super, ['*']);
        $this->patchJson("/api/v1/super-admin/tenants/{$tenant->public_id}/status", ['status' => 'suspended'])->assertForbidden()->assertJsonPath('errors.0.code', 'two_factor_required');
        $super->forceFill(['two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($super->refresh(), ['*']);
        $this->patchJson("/api/v1/super-admin/tenants/{$tenant->public_id}/status", ['status' => 'suspended'])->assertOk();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'tenant.status_changed', 'user_id' => $super->id]);
    }

    public function test_tenant_admin_manages_members_and_typed_settings(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $owner = User::factory()->create();
        $owner->tenants()->attach($tenant, ['role' => 'admin']);
        Sanctum::actingAs($owner, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => 'shop'];

        $member = $this->postJson('/api/v1/members', ['name' => 'Counter Staff', 'email' => 'staff@shop.test', 'role' => 'staff'], $headers)
            ->assertCreated()->assertJsonPath('data.member.role', 'staff')->assertJsonPath('data.invitation_sent', true)->json('data.member');
        Notification::assertSentTo(User::where('email', 'staff@shop.test')->firstOrFail(), ResetPasswordNotification::class);
        $this->patchJson("/api/v1/members/{$member['id']}", ['role' => 'manager'], $headers)->assertOk()->assertJsonPath('data.member.role', 'manager');
        $this->patchJson('/api/v1/settings', ['name' => 'New Shop Name', 'settings' => ['order_prefix' => 'NS', 'default_delivery_days' => 5]], $headers)
            ->assertOk()->assertJsonPath('data.settings.preferences.order_prefix', 'NS');
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Shop Name']);
        $this->deleteJson("/api/v1/members/{$member['id']}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => User::where('email', 'staff@shop.test')->value('id')]);
    }

    public function test_cashier_subscription_state_drives_feature_limits_and_unconfigured_checkout_is_safe(): void
    {
        $plan = Plan::create(['code' => 'growth', 'name' => 'Growth', 'price_minor' => 50000, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => ['inventory' => true, 'orders_per_month' => 500], 'active' => true]);
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $owner = User::factory()->create();
        $owner->tenants()->attach($tenant, ['role' => 'admin']);
        $tenant->subscriptions()->create(['plan_id' => $plan->id, 'provider' => 'stripe', 'type' => 'default', 'stripe_id' => 'sub_test', 'stripe_status' => 'active', 'stripe_price' => 'price_test', 'status' => 'pending']);
        Sanctum::actingAs($owner, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => 'shop'];

        $this->getJson('/api/v1/billing', $headers)->assertOk()->assertJsonPath('data.subscription.status', 'active')->assertJsonPath('data.limits.inventory', true);
        $this->postJson('/api/v1/billing/checkout', ['plan_code' => 'growth'], $headers)->assertStatus(503)->assertJsonPath('errors.0.code', 'billing_not_configured');
    }

    public function test_exports_are_generated_emailed_and_deletion_has_a_cancellation_window(): void
    {
        Storage::fake('local');
        Mail::fake();
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $owner = User::factory()->create(['password' => 'VerySecret123!']);
        $owner->tenants()->attach($tenant, ['role' => 'admin']);
        Sanctum::actingAs($owner, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => 'shop'];

        $export = $this->postJson('/api/v1/exports', ['type' => 'summary', 'email' => 'owner@shop.test'], $headers)->assertAccepted()->json('data.export');
        $this->getJson('/api/v1/exports', $headers)->assertOk()->assertJsonPath('data.items.0.status', 'completed');
        $this->get('/api/v1/exports/'.$export['id'].'/download', $headers)->assertOk();
        Mail::assertSentCount(1);
        $deletion = $this->postJson('/api/v1/tenant-deletion', ['tenant_slug' => 'shop', 'password' => 'VerySecret123!'], $headers)->assertAccepted()->json('data.deletion');
        $this->deleteJson('/api/v1/tenant-deletion/'.$deletion['id'], [], $headers)->assertOk();
        $this->assertDatabaseHas('tenant_deletion_requests', ['tenant_id' => $tenant->id, 'status' => 'cancelled']);
    }

    public function test_due_tenant_deletion_removes_tenant_data_and_private_files(): void
    {
        Storage::fake('local');
        $tenant = Tenant::create(['name' => 'Departing Shop', 'slug' => 'departing', 'status' => 'active']);
        $owner = User::factory()->create(['password' => 'VerySecret123!']);
        $owner->tenants()->attach($tenant, ['role' => 'admin']);
        Storage::disk('local')->put("tenants/{$tenant->public_id}/receipt.pdf", 'private');
        $this->app->make(TenantContext::class)->set($tenant);
        $request = TenantDeletionRequest::create(['requested_by' => $owner->id, 'status' => 'scheduled', 'scheduled_for' => now()->subMinute()]);

        $this->artisan('tenants:process-deletions')->assertSuccessful();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('tenant_deletion_requests', ['id' => $request->id]);
        Storage::disk('local')->assertMissing("tenants/{$tenant->public_id}/receipt.pdf");
    }

    public function test_stripe_webhook_payment_failed_marks_subscription_past_due_and_audits(): void
    {
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $tenant->subscriptions()->create(['plan_id' => null, 'provider' => 'stripe', 'type' => 'default', 'stripe_id' => 'sub_123', 'stripe_status' => 'active', 'stripe_price' => 'price_test', 'status' => 'active']);

        $request = Request::create('/stripe/webhook', 'POST', [], [], [], [], json_encode(['type' => 'invoice.payment_failed', 'data' => ['object' => ['id' => 'sub_123', 'invoice' => 'in_456']]]));
        $response = (new StripeWebhookController)->handleWebhook($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_123', 'status' => 'past_due']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'billing.payment_failed']);
    }

    public function test_stripe_webhook_subscription_updated_syncs_state(): void
    {
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $tenant->subscriptions()->create(['plan_id' => null, 'provider' => 'stripe', 'type' => 'default', 'stripe_id' => 'sub_abc', 'stripe_status' => 'trialing', 'stripe_price' => 'price_test', 'status' => 'trialing']);

        $request = Request::create('/stripe/webhook', 'POST', [], [], [], [], json_encode(['type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_abc', 'status' => 'active', 'cancel_at_period_end' => false, 'current_period_end' => now()->addMonth()->timestamp]]]));
        (new StripeWebhookController)->handleWebhook($request);

        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_abc', 'status' => 'active', 'stripe_status' => 'active']);
    }

    public function test_stripe_webhook_rejects_unauthenticated_request(): void
    {
        $this->postJson('/stripe/webhook', ['type' => 'invoice.payment_failed'])->assertForbidden();
    }
}
