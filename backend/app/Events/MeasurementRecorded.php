<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderMeasurement;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeasurementRecorded implements ShouldBroadcastNow
{
    use Dispatchable,SerializesModels;

    public function __construct(public Order $order, public OrderMeasurement $measurement) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenants.{$this->order->tenant->public_id}.orders.{$this->order->public_id}")];
    }

    public function broadcastAs(): string
    {
        return 'measurement.recorded';
    }
}
