<?php

namespace Tests\Feature;

use App\Jobs\DeliverOrderReadyWebhook;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_is_signed_and_attempt_is_logged(): void
    {
        $tenant = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $endpoint = WebhookEndpoint::create(['url' => 'https://voice.example.test/events', 'signing_secret' => 'top-secret', 'events' => ['order.ready'], 'active' => true]);
        app(TenantContext::class)->clear();
        Http::fake(['voice.example.test/*' => Http::response(['accepted' => true], 202)]);
        $payload = ['event' => 'order.ready', 'event_id' => '01TEST', 'data' => ['order_id' => '01ORDER']];
        $job = new DeliverOrderReadyWebhook($tenant->id, $endpoint->id, '01TEST', $payload);
        $job->withFakeQueueInteractions();
        $job->handle(app(TenantContext::class));
        Http::assertSent(function ($request) use ($payload) {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

            return $request->hasHeader('X-Tailors-Signature', 'sha256='.hash_hmac('sha256', $body, 'top-secret'));
        });
        $this->assertDatabaseHas('webhook_deliveries', ['tenant_id' => $tenant->id, 'event_id' => '01TEST', 'response_status' => 202]);
    }

    public function test_admin_manages_webhook_endpoints_with_secret_rotation(): void
    {
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        Sanctum::actingAs($user, ['app:read', 'app:write']);
        $headers = ['X-Tenant' => $tenant->slug];

        $created = $this->postJson('/api/v1/webhooks', ['url' => 'https://hooks.example.test/tailors', 'events' => ['order.ready', 'payment.received'], 'active' => true], $headers)
            ->assertCreated()->json('data');
        $this->assertArrayHasKey('signing_secret', $created);
        $this->assertSame(64, strlen($created['signing_secret']));
        $this->assertSame('https://hooks.example.test/tailors', $created['endpoint']['url']);
        $endpointId = $created['endpoint']['id'];

        $this->patchJson("/api/v1/webhooks/{$endpointId}", ['events' => ['order.delivered']], $headers)->assertOk()->assertJsonPath('data.endpoint.events', ['order.delivered']);

        $rotated = $this->postJson("/api/v1/webhooks/{$endpointId}/rotate-secret", [], $headers)->assertOk()->json('data');
        $this->assertNotSame($created['signing_secret'], $rotated['signing_secret']);

        $this->getJson('/api/v1/webhooks', $headers)->assertOk()->assertJsonPath('data.items.0.id', $endpointId);

        $this->deleteJson("/api/v1/webhooks/{$endpointId}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpointId]);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'webhook.created']);
    }

    public function test_webhook_endpoints_are_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenantA, ['role' => 'admin']);
        app(TenantContext::class)->set($tenantB);
        $foreign = WebhookEndpoint::create(['url' => 'https://b.example.test/hook', 'signing_secret' => 's', 'events' => ['order.ready'], 'active' => true]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['app:read', 'app:write']);

        $this->getJson('/api/v1/webhooks', ['X-Tenant' => 'a'])->assertOk()->assertJsonCount(0, 'data.items');
        $this->deleteJson("/api/v1/webhooks/{$foreign->id}", [], ['X-Tenant' => 'a'])->assertNotFound();
    }
}
