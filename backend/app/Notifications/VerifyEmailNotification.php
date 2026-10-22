<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Signed, expiring link that confirms the address belongs to the user. Hits the API, which redirects to the app. */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addDays(3), [
            'user' => $notifiable->public_id,
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your Tailors email address')
            ->line('Please confirm that this email address belongs to you. The link is valid for 3 days.')
            ->action('Confirm email', $this->verificationUrl($notifiable))
            ->line('If you did not create or join a workshop, you can ignore this message.');
    }
}
