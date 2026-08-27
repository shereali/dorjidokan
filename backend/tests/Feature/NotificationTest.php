<?php

namespace Tests\Feature;

use App\Events\OrderReady;
use App\Jobs\SendNotificationDelivery;
use App\Models\Customer;
use App\Models\Garment;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_ready_queues_rendered_transactional_notification(): void
    {
        Queue::fake();
        $tenant = Tenant::create(['name' => 'Workshop', 'slug' => 'workshop', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $customer = Customer::create(['name' => 'Hasan', 'mobile_number' => '+8801711111111']);
        $garment = Garment::create(['name' => 'Panjabi', 'slug' => 'panjabi', 'active' => true]);
        $order = Order::create(['order_number' => 'ORD-100', 'customer_id' => $customer->id, 'garment_id' => $garment->id, 'status' => 'ready']);
        NotificationTemplate::create(['event' => 'order.ready', 'channel' => 'sms', 'name' => 'Ready', 'body' => 'Hello {{customer_name}}, {{order_number}} is ready.', 'active' => true]);

        event(new OrderReady($order));

        $this->assertDatabaseHas('notification_deliveries', ['tenant_id' => $tenant->id, 'recipient' => '+8801711111111', 'body' => 'Hello Hasan, ORD-100 is ready.', 'status' => 'queued']);
        Queue::assertPushed(SendNotificationDelivery::class);
    }
}
