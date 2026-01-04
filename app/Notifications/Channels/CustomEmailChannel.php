<?php

namespace App\Notifications\Channels;

use App\Contracts\EmailServiceInterface;
use App\Models\NotificationLog;
use Illuminate\Notifications\Notification;
use Exception;

class CustomEmailChannel
{
    protected EmailServiceInterface $emailService;

    public function __construct(EmailServiceInterface $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification&\App\Notifications\BaseNotification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        // Check if notification has toMail method
        if (!method_exists($notification, 'toMail')) {
            return;
        }

        /** @var array{subject: string, view: string, data?: array, options?: array} $message */
        $message = call_user_func([$notification, 'toMail'], $notifiable);

        try {
            $result = $this->emailService->sendTemplate(
                $notifiable->email,
                $message['subject'],
                $message['view'],
                $message['data'] ?? [],
                $message['options'] ?? []
            );

            // Log successful send
            NotificationLog::logNotification(
                $notification->id ?? null,
                $notifiable->id,
                'email',
                get_class($notification),
                'sent',
                $this->emailService->getProvider(),
                $result['message_id'] ?? null,
                null,
                [
                    'to' => $notifiable->email,
                    'subject' => $message['subject'],
                ]
            );
        } catch (Exception $e) {
            // Log failed send
            NotificationLog::logNotification(
                $notification->id ?? null,
                $notifiable->id,
                'email',
                get_class($notification),
                'failed',
                $this->emailService->getProvider(),
                null,
                $e->getMessage(),
                [
                    'to' => $notifiable->email,
                    'subject' => $message['subject'],
                ]
            );

            // Optionally re-throw to retry via queue
            // throw $e;
        }
    }
}
