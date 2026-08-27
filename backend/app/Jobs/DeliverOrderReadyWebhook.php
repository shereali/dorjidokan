<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeliverOrderReadyWebhook implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 5;

    public function __construct(public int $tenantId, public int $endpointId, public string $eventId, public array $payload) {}

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(TenantContext $context): void
    {
        $endpoint = WebhookEndpoint::withoutGlobalScope('tenant')->findOrFail($this->endpointId);
        $context->set($endpoint->tenant()->firstOrFail());
        $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES);
        $attempt = $this->attempts();
        $delivery = WebhookDelivery::withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenantId, 'webhook_endpoint_id' => $endpoint->id, 'event_id' => $this->eventId, 'event_name' => 'order.ready', 'payload' => $this->payload, 'attempt' => $attempt]);
        try {
            $response = Http::timeout(10)->withHeaders(['X-Tailors-Event' => 'order.ready', 'X-Tailors-Event-Id' => $this->eventId, 'X-Tailors-Signature' => 'sha256='.hash_hmac('sha256', $body, $endpoint->signing_secret)])->withBody($body, 'application/json')->post($endpoint->url);
            $delivery->update(['response_status' => $response->status(), 'delivered_at' => $response->successful() ? now() : null, 'error' => $response->successful() ? null : $response->body(), 'next_retry_at' => $response->successful() ? null : now()->addSeconds($this->backoff()[min($attempt - 1, count($this->backoff()) - 1)])]);
            if (! $response->successful()) {
                throw new RuntimeException("Webhook returned {$response->status()}.");
            }
        } finally {
            $context->clear();
        }
    }
}
