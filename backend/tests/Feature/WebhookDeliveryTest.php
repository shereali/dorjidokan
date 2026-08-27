<?php

namespace Tests\Feature;

use App\Jobs\DeliverOrderReadyWebhook;
use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
