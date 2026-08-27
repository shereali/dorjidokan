<?php

namespace App\Jobs;

use App\Contracts\NotificationProvider;
use App\Models\Customer;
use App\Models\NotificationCampaign;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendOccasionCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $campaignId) {}

    public function handle(NotificationProvider $provider): void
    {
        $campaign = NotificationCampaign::withoutGlobalScopes()->findOrFail($this->campaignId);
        if ($campaign->status !== 'queued') {
            return;
        }

        // Atomically claim the campaign so concurrent workers can't double-send.
        $claimed = DB::transaction(function () use ($campaign) {
            $fresh = NotificationCampaign::withoutGlobalScopes()->whereKey($campaign->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== 'queued') {
                return false;
            }

            return $fresh->update(['status' => 'sending']) !== false;
        });

        if (! $claimed) {
            return;
        }

        $recipients = Customer::withoutGlobalScopes()
            ->where('tenant_id', $campaign->tenant_id)
            ->where('marketing_consent', true)
            ->whereNotNull('mobile_number')
            ->pluck('mobile_number', 'id');

        $sent = 0;
        $total = count($recipients);
        foreach ($recipients as $customerId => $mobile) {
            try {
                $reference = $provider->send('sms', $mobile, $campaign->body);
                $sent++;
                NotificationDelivery::withoutGlobalScopes()->create([
                    'tenant_id' => $campaign->tenant_id,
                    'notification_template_id' => null,
                    'event' => 'campaign.'.$campaign->public_id,
                    'channel' => 'sms',
                    'recipient' => $mobile,
                    'body' => $campaign->body,
                    'status' => 'sent',
                    'provider_reference' => $reference,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                NotificationDelivery::withoutGlobalScopes()->create([
                    'tenant_id' => $campaign->tenant_id,
                    'notification_template_id' => null,
                    'event' => 'campaign.'.$campaign->public_id,
                    'channel' => 'sms',
                    'recipient' => $mobile,
                    'body' => $campaign->body,
                    'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
            }
        }

        NotificationCampaign::withoutGlobalScopes()
            ->whereKey($campaign->id)
            ->update([
                'status' => $total > 0 && $sent === $total ? 'sent' : ($total > 0 ? 'partial' : 'sent'),
                'recipient_count' => $sent,
            ]);
    }
}
