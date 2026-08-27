<?php

namespace Tests\Feature;

use App\Contracts\NotificationProvider;
use App\Events\OrderReady;
use App\Jobs\SendDeliveryReminder;
use App\Jobs\SendNotificationDelivery;
use App\Jobs\SendOccasionCampaign;
use App\Models\Customer;
use App\Models\DeliveryReminder;
use App\Models\Garment;
use App\Models\NotificationCampaign;
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

    public function test_occasion_campaign_with_consent_queues_fanout(): void
    {
        Queue::fake();
        $tenant = Tenant::create(['name' => 'Occasion Shop', 'slug' => 'occasion-shop', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $consented = Customer::create(['name' => 'Ayesha', 'mobile_number' => '+8801722222222', 'marketing_consent' => true]);
        Customer::create(['name' => 'No Consent', 'mobile_number' => '+8801733333333', 'marketing_consent' => false]);

        $campaign = NotificationCampaign::create(['name' => 'Eid Campaign', 'occasion' => 'eid', 'channel' => 'sms', 'body' => 'Happy Eid from our shop!', 'status' => 'queued', 'recipient_count' => 0, 'created_by' => null, 'queued_at' => now()]);

        (new SendOccasionCampaign($campaign->id))->handle(app(NotificationProvider::class));

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertDatabaseHas('notification_deliveries', ['tenant_id' => $tenant->id, 'recipient' => $consented->mobile_number, 'status' => 'sent']);
        $this->assertDatabaseMissing('notification_deliveries', ['recipient' => '+8801733333333']);
    }

    public function test_delivery_reminder_auto_schedules_on_order_creation(): void
    {
        Queue::fake();
        $tenant = Tenant::create(['name' => 'Reminder Shop', 'slug' => 'reminder-shop', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $customer = Customer::create(['name' => 'Karim', 'mobile_number' => '+8801744444444']);
        $garment = Garment::create(['name' => 'Shirt', 'slug' => 'shirt', 'active' => true]);
        $order = Order::create(['order_number' => 'ORD-200', 'customer_id' => $customer->id, 'garment_id' => $garment->id, 'status' => 'measuring', 'promised_at' => now()->addDays(3)]);

        DeliveryReminder::create(['order_id' => $order->id, 'customer_id' => $customer->id, 'channel' => 'sms', 'status' => 'scheduled', 'scheduled_at' => $order->promised_at]);

        $this->assertDatabaseHas('delivery_reminders', ['tenant_id' => $tenant->id, 'order_id' => $order->id, 'status' => 'scheduled']);
    }

    public function test_delivery_reminder_job_renders_template_and_sends(): void
    {
        Queue::fake();
        $tenant = Tenant::create(['name' => 'Reminder Shop 2', 'slug' => 'reminder-shop-2', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $customer = Customer::create(['name' => 'Rahim', 'mobile_number' => '+8801755555555']);
        $garment = Garment::create(['name' => 'Pant', 'slug' => 'pant', 'active' => true]);
        $order = Order::create(['order_number' => 'ORD-300', 'customer_id' => $customer->id, 'garment_id' => $garment->id, 'status' => 'measuring', 'promised_at' => now()->addDay()]);
        NotificationTemplate::create(['event' => 'order.reminder', 'channel' => 'sms', 'name' => 'Reminder', 'body' => 'Reminder: {{customer_name}}, order {{order_number}} due {{promised_at}}.', 'active' => true]);
        $reminder = DeliveryReminder::create(['order_id' => $order->id, 'customer_id' => $customer->id, 'channel' => 'sms', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);

        $job = new SendDeliveryReminder($reminder->id);
        $job->handle(app(NotificationProvider::class));

        $this->assertSame('sent', $reminder->refresh()->status);
        $this->assertNotNull($reminder->provider_reference);
        $this->assertDatabaseHas('notification_deliveries', ['recipient' => '+8801755555555', 'status' => 'sent']);
    }
}
