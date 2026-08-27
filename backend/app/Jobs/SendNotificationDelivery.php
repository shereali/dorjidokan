<?php

namespace App\Jobs;

use App\Contracts\NotificationProvider;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $deliveryId) {}

    public function handle(NotificationProvider $provider): void
    {
        $delivery = NotificationDelivery::withoutGlobalScope('tenant')->findOrFail($this->deliveryId);
        try {
            $reference = $provider->send($delivery->channel, $delivery->recipient, $delivery->body);
            $delivery->update(['status' => 'sent', 'provider_reference' => $reference, 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }
}
