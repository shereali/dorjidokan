<?php

namespace App\Contracts;

interface NotificationProvider
{
    public function send(string $channel, string $recipient, string $body): string;
}
