<?php

namespace App\Console\Commands;

use App\Jobs\SendDeliveryReminder;
use App\Jobs\SendOccasionCampaign;
use App\Models\DeliveryReminder;
use App\Models\NotificationCampaign;
use Illuminate\Console\Command;

class ProcessScheduledNotifications extends Command
{
    protected $signature = 'notifications:process-scheduled';

    protected $description = 'Send due delivery reminders and launch due occasion campaigns';

    public function handle(): int
    {
        $reminders = DeliveryReminder::withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->pluck('id');

        foreach ($reminders as $reminderId) {
            SendDeliveryReminder::dispatch($reminderId);
        }

        $campaigns = NotificationCampaign::withoutGlobalScopes()
            ->where('status', 'queued')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->pluck('id');

        foreach ($campaigns as $campaignId) {
            SendOccasionCampaign::dispatch($campaignId);
        }

        $this->info("Dispatched {$reminders->count()} delivery reminder(s) and {$campaigns->count()} occasion campaign(s).");

        return self::SUCCESS;
    }
}
