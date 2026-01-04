<?php

namespace App\Providers;

use App\Contracts\EmailServiceInterface;
use App\Services\Email\ResendEmailService;
use App\Services\Email\SmtpEmailService;
use App\Notifications\Channels\CustomEmailChannel;
use App\Notifications\Channels\PushNotificationChannel;
use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind EmailServiceInterface to implementation based on config
        $this->app->bind(EmailServiceInterface::class, function ($app) {
            $driver = config('notifications.email_driver', 'resend');

            return match ($driver) {
                'resend' => new ResendEmailService(),
                'smtp' => new SmtpEmailService(),
                default => new ResendEmailService(),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom notification channels
        $this->app->make(ChannelManager::class)->extend('custom-email', function ($app) {
            return new CustomEmailChannel($app->make(EmailServiceInterface::class));
        });

        $this->app->make(ChannelManager::class)->extend('push', function ($app) {
            return new PushNotificationChannel();
        });
    }
}
