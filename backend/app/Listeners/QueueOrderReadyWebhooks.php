<?php

namespace App\Listeners;

use App\Events\OrderReady;
use App\Jobs\DeliverOrderReadyWebhook;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class QueueOrderReadyWebhooks
{
    public function handle(OrderReady $event): void
    {
        $id = (string) Str::ulid();
        $payload = ['event' => 'order.ready', 'event_id' => $id, 'occurred_at' => now()->toIso8601String(), 'data' => ['order_id' => $event->order->public_id, 'order_number' => $event->order->order_number, 'status' => $event->order->status]];
        WebhookEndpoint::withoutGlobalScope('tenant')->where('tenant_id', $event->order->tenant_id)->where('active', true)->get()->filter(fn ($e) => in_array('order.ready', $e->events, true))->each(fn ($e) => DeliverOrderReadyWebhook::dispatch($event->order->tenant_id, $e->id, $id, $payload));
    }
}
