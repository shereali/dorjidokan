<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    public function __construct(string $token, private readonly ?string $tenantSlug = null)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $tenant = $this->tenantSlug ?? request()->input('tenant');
        $url = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/').'/?'.http_build_query([
            'reset_token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'tenant' => $tenant,
        ]);

        return (new MailMessage)->subject('Reset your Tailors password')->line('A password reset was requested for your workshop account.')->action('Reset password', $url)->line('This link expires automatically. Ignore this message if you did not request it.');
    }
}
