<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    /**
     * Get the notification type identifier.
     * Must be implemented by child classes.
     *
     * @return string
     */
    abstract public function getNotificationType(): string;

    /**
     * Get the notification's delivery channels based on user preferences.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = [];
        $notificationType = $this->getNotificationType();

        // Check if channels are globally enabled
        $globalConfig = config('notifications.channels', []);

        // Check email channel
        if (
            ($globalConfig['email'] ?? false) &&
            NotificationPreference::isEnabled($notifiable->id, $notificationType, 'mail')
        ) {
            $channels[] = 'custom-email';
        }

        // Check database channel (in-app notifications)
        if (
            ($globalConfig['database'] ?? false) &&
            NotificationPreference::isEnabled($notifiable->id, $notificationType, 'database')
        ) {
            $channels[] = 'database';
        }

        // Check push notification channel
        if (
            ($globalConfig['push'] ?? false) &&
            NotificationPreference::isEnabled($notifiable->id, $notificationType, 'push')
        ) {
            $channels[] = 'push';
        }

        return $channels;
    }

    /**
     * Determine if notification should be queued.
     *
     * @return bool
     */
    public function shouldQueue(): bool
    {
        return config('notifications.queue.enabled', true);
    }

    /**
     * Get the queue connection to use.
     *
     * @return string
     */
    public function viaQueue(): string
    {
        return config('notifications.queue.connection', 'database');
    }

    /**
     * Get the queue name to use.
     *
     * @return string
     */
    public function onQueue(): string
    {
        return config('notifications.queue.queue', 'notifications');
    }

    /**
     * Get the array representation of the notification (for database channel).
     *
     * @param mixed $notifiable
     * @return array
     */
    abstract public function toArray($notifiable): array;
}
