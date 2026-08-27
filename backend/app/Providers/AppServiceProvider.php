<?php

namespace App\Providers;

use App\Contracts\NotificationProvider;
use App\Services\LogNotificationProvider;
use App\Services\Sms\HttpSmsProvider;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(NotificationProvider::class, function () {
            return match (config('services.sms.provider', 'log')) {
                'http', 'twilio', 'clicksend', 'custom' => new HttpSmsProvider,
                default => new LogNotificationProvider,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
