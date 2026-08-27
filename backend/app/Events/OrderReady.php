<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderReady implements ShouldBroadcast
{
    use Dispatchable,SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenants.{$this->order->tenant->public_id}.orders.{$this->order->public_id}")];
    }

    public function broadcastAs(): string
    {
        return 'order.ready';
    }
}
