<?php

namespace App\Listeners;

use App\Events\OrderReady;
use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;

class QueueOrderReadyNotification
{
    public function handle(OrderReady $event): void
    {
        $order = $event->order->loadMissing('customer');
        $template = NotificationTemplate::withoutGlobalScope('tenant')->where('tenant_id', $order->tenant_id)->where('event', 'order.ready')->where('channel', 'sms')->where('active', true)->first();
        if (! $template || ! $order->customer->mobile_number) {
            return;
        }
        $body = strtr($template->body, ['{{customer_name}}' => $order->customer->name, '{{order_number}}' => $order->order_number]);
        $delivery = NotificationDelivery::withoutGlobalScope('tenant')->create(['tenant_id' => $order->tenant_id, 'notification_template_id' => $template->id, 'event' => 'order.ready', 'channel' => 'sms', 'recipient' => $order->customer->mobile_number, 'body' => $body, 'status' => 'queued']);
        SendNotificationDelivery::dispatch($delivery->id);
    }
}
