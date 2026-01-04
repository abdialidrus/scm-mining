<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use App\Models\UserDevice;
use App\Services\Push\OneSignalService;
use Illuminate\Notifications\Notification;
use Exception;

class PushNotificationChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification&\App\Notifications\BaseNotification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toPush')) {
            return;
        }

        // Check if user has any active devices
        $hasActiveDevices = UserDevice::where('user_id', $notifiable->id)
            ->where('is_active', true)
            ->exists();

        if (!$hasActiveDevices) {
            NotificationLog::logNotification(
                $notification->id ?? null,
                $notifiable->id,
                'push',
                get_class($notification),
                'skipped',
                'onesignal',
                null,
                'No active devices',
                null
            );
            return;
        }

        /** @var array{title: string, body: string, data?: array, options?: array} $message */
        $message = call_user_func([$notification, 'toPush'], $notifiable);

        try {
            $oneSignal = new OneSignalService();

            $result = $oneSignal->sendToUsers(
                [$notifiable->id],
                $message['title'],
                $message['body'],
                $message['data'] ?? [],
                $message['options'] ?? []
            );

            if ($result['success']) {
                NotificationLog::logNotification(
                    $notification->id ?? null,
                    $notifiable->id,
                    'push',
                    get_class($notification),
                    'sent',
                    'onesignal',
                    $result['notification_id'] ?? null,
                    null,
                    [
                        'recipients' => $result['recipients'] ?? 0,
                    ]
                );
            } else {
                NotificationLog::logNotification(
                    $notification->id ?? null,
                    $notifiable->id,
                    'push',
                    get_class($notification),
                    'failed',
                    'onesignal',
                    null,
                    $result['error'] ?? 'Unknown error',
                    null
                );
            }
        } catch (Exception $e) {
            NotificationLog::logNotification(
                $notification->id ?? null,
                $notifiable->id,
                'push',
                get_class($notification),
                'failed',
                'onesignal',
                null,
                $e->getMessage(),
                null
            );
        }
    }
}
