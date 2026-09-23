<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\NotificationCampaign;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $templates = [
            [
                'event' => 'order.ready',
                'name' => 'Order Ready for Trial / Pickup',
                'channel' => 'sms',
                'body' => 'Dear {{customer_name}}, your order #{{order_number}} ({{garment_name}}) is tailored and ready for trial/pickup at Heritage Tailors. Balance due: ৳{{due_amount}}.',
            ],
            [
                'event' => 'payment.received',
                'name' => 'Advance Payment Confirmed',
                'channel' => 'sms',
                'body' => 'Thank you {{customer_name}}! Advance ৳{{paid_amount}} received for order #{{order_number}}. Delivery promise: {{promised_date}}. - Heritage Tailors',
            ],
            [
                'event' => 'delivery.reminder',
                'name' => 'Trial Reminder (1 Day Before)',
                'channel' => 'sms',
                'body' => 'Gentle reminder: Your bespoke outfit #{{order_number}} is scheduled for delivery tomorrow at Heritage Tailors Atelier. We look forward to seeing you!',
            ],
            [
                'event' => 'occasion.eid',
                'name' => 'Eid & Wedding Bespoke Booking Campaign',
                'channel' => 'sms',
                'body' => 'Eid Mubarak {{customer_name}}! Book your bespoke Panjabi & Sherwani early at Heritage Tailors and enjoy 10% loyalty credit. Contact: 01711000101.',
            ],
        ];

        foreach ($templates as $tmpl) {
            NotificationTemplate::firstOrCreate(
                ['tenant_id' => $tenant->id, 'event' => $tmpl['event'], 'channel' => $tmpl['channel']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $tmpl['name'],
                    'body' => $tmpl['body'],
                    'active' => true,
                ]
            );
        }

        // Eid Pre-Booking Campaign
        $eidTmpl = NotificationTemplate::where('tenant_id', $tenant->id)->where('event', 'occasion.eid')->first();
        if ($eidTmpl) {
            $campaign = NotificationCampaign::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Eid-ul-Fitr Royal Bespoke Pre-Booking'],
                [
                    'public_id' => (string) Str::ulid(),
                    'occasion' => 'Eid-ul-Fitr',
                    'channel' => 'sms',
                    'body' => $eidTmpl->body,
                    'status' => 'sent',
                    'recipient_count' => 10,
                    'queued_at' => now()->subDays(3),
                    'scheduled_at' => now()->subDays(3),
                ]
            );

            if ($campaign->wasRecentlyCreated) {
                $optedIn = Customer::where('tenant_id', $tenant->id)->where('marketing_consent', true)->limit(5)->get();
                foreach ($optedIn as $c) {
                    NotificationDelivery::create([
                        'tenant_id' => $tenant->id,
                        'notification_template_id' => $eidTmpl->id,
                        'event' => 'occasion.eid',
                        'channel' => 'sms',
                        'recipient' => $c->mobile_number,
                        'body' => str_replace('{{customer_name}}', $c->name, $eidTmpl->body),
                        'status' => 'delivered',
                    ]);
                }
            }
        }
    }
}
