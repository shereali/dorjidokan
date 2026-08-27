<?php

namespace App\Jobs;

use App\Contracts\NotificationProvider;
use App\Models\DeliveryReminder;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDeliveryReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $reminderId) {}

    public function handle(NotificationProvider $provider): void
    {
        $reminder = DeliveryReminder::withoutGlobalScope('tenant')->findOrFail($this->reminderId);
        if ($reminder->status !== 'scheduled') {
            return;
        }

        $order = $reminder->order()->withoutGlobalScope('tenant')->with('customer')->first();
        if (! $order?->customer?->mobile_number) {
            $reminder->update(['status' => 'skipped', 'error' => 'Customer has no mobile number.']);

            return;
        }

        $template = NotificationTemplate::withoutGlobalScope('tenant')
            ->where('tenant_id', $reminder->tenant_id)
            ->where('event', 'order.reminder')
            ->where('channel', $reminder->channel)
            ->where('active', true)
            ->first();

        $body = $template?->body ?? 'Dear {{customer_name}}, your order {{order_number}} is due for delivery soon.';
        $body = strtr($body, [
            '{{customer_name}}' => $order->customer->name,
            '{{order_number}}' => $order->order_number,
            '{{promised_at}}' => $order->promised_at?->format('d/m/Y') ?? '',
        ]);

        try {
            $reference = $provider->send($reminder->channel, $order->customer->mobile_number, $body);
            NotificationDelivery::withoutGlobalScope('tenant')->create([
                'tenant_id' => $reminder->tenant_id,
                'notification_template_id' => $template?->id,
                'event' => 'order.reminder',
                'channel' => $reminder->channel,
                'recipient' => $order->customer->mobile_number,
                'body' => $body,
                'status' => 'sent',
                'provider_reference' => $reference,
                'sent_at' => now(),
            ]);
            $reminder->update(['status' => 'sent', 'provider_reference' => $reference, 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $exception) {
            $reminder->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }
}
