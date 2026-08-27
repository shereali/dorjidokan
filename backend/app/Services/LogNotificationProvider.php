<?php

namespace App\Services;

use App\Contracts\NotificationProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogNotificationProvider implements NotificationProvider
{
    public function send(string $channel, string $recipient, string $body): string
    {
        $reference = 'log_'.Str::lower((string) Str::ulid());
        Log::info('Notification provider delivery', compact('reference', 'channel', 'recipient', 'body'));

        return $reference;
    }
}
