# Email & Push Notifications System - Implementation Guide

**Document Version:** 1.0  
**Date Created:** January 4, 2026  
**Status:** Design & Planning

---

## 📋 Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Email Service Strategy](#email-service-strategy)
3. [Push Notification Strategy](#push-notification-strategy)
4. [Unified Notification System](#unified-notification-system)
5. [Implementation Plan](#implementation-plan)
6. [Configuration](#configuration)
7. [Testing Strategy](#testing-strategy)
8. [Migration Path](#migration-path)

---

## 🏗️ Architecture Overview

### Design Philosophy

**Goal:** Create a unified notification system yang:

- ✅ Support multiple channels (Email, Push, Database, Slack)
- ✅ Easy to switch email providers (Resend → SMTP → SES)
- ✅ Centralized notification logic
- ✅ User preferences per channel
- ✅ Queue-based for performance
- ✅ Retry mechanism for failures
- ✅ Audit trail

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Application Layer                             │
│  (Services: ApprovalWorkflowService, PurchaseOrderService, etc.) │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              Unified Notification Dispatcher                     │
│              (Laravel Notification System)                       │
│                                                                  │
│  • Determine recipients                                          │
│  • Check user preferences                                        │
│  • Route to appropriate channels                                 │
│  • Queue for async processing                                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
            ┌────────────┴────────────┬─────────────┐
            ▼                         ▼             ▼
┌────────────────────┐    ┌────────────────┐    ┌──────────────┐
│  Email Channel     │    │  Push Channel  │    │  DB Channel  │
│  (Abstracted)      │    │  (OneSignal)   │    │  (In-app)    │
└─────────┬──────────┘    └────────┬───────┘    └──────┬───────┘
          │                        │                    │
          ▼                        ▼                    ▼
┌────────────────────┐    ┌────────────────┐    ┌──────────────┐
│  Email Provider    │    │  OneSignal API │    │  Database    │
│  • Resend (now)    │    │                │    │              │
│  • SMTP (future)   │    │                │    │              │
│  • AWS SES (future)│    │                │    │              │
└────────────────────┘    └────────────────┘    └──────────────┘
```

---

## 📧 Email Service Strategy

### 1. Email Service Abstraction Layer

**Goal:** Decouple application dari specific email provider

#### Interface Design

```php
// app/Contracts/EmailServiceInterface.php
namespace App\Contracts;

interface EmailServiceInterface
{
    /**
     * Send an email
     *
     * @param array $params {
     *   @type string $to Email recipient
     *   @type string $subject Email subject
     *   @type string $html HTML content
     *   @type string|null $text Plain text content
     *   @type string|null $from Sender email
     *   @type string|null $fromName Sender name
     *   @type array|null $cc CC recipients
     *   @type array|null $bcc BCC recipients
     *   @type array|null $attachments File attachments
     *   @type array|null $tags Tags for tracking
     * }
     * @return array Response with message_id
     */
    public function send(array $params): array;

    /**
     * Send batch emails
     */
    public function sendBatch(array $emails): array;

    /**
     * Get email sending status
     */
    public function getStatus(string $messageId): array;

    /**
     * Verify sender domain/email
     */
    public function verify(string $email): bool;
}
```

---

### 2. Resend Implementation

```php
// app/Services/Email/ResendEmailService.php
namespace App\Services\Email;

use App\Contracts\EmailServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResendEmailService implements EmailServiceInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://api.resend.com';

    public function __construct()
    {
        $this->apiKey = config('services.resend.api_key');
    }

    public function send(array $params): array
    {
        try {
            $payload = [
                'from' => $params['from'] ?? config('mail.from.address'),
                'to' => [$params['to']],
                'subject' => $params['subject'],
                'html' => $params['html'],
            ];

            // Optional parameters
            if (isset($params['text'])) {
                $payload['text'] = $params['text'];
            }
            if (isset($params['cc'])) {
                $payload['cc'] = $params['cc'];
            }
            if (isset($params['bcc'])) {
                $payload['bcc'] = $params['bcc'];
            }
            if (isset($params['tags'])) {
                $payload['tags'] = $params['tags'];
            }
            if (isset($params['attachments'])) {
                $payload['attachments'] = $this->formatAttachments($params['attachments']);
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/emails", $payload);

            if ($response->failed()) {
                throw new \Exception("Resend API error: {$response->body()}");
            }

            return [
                'success' => true,
                'message_id' => $response->json('id'),
                'provider' => 'resend',
            ];

        } catch (\Exception $e) {
            Log::error('Resend email failed', [
                'error' => $e->getMessage(),
                'to' => $params['to'],
                'subject' => $params['subject'],
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'resend',
            ];
        }
    }

    public function sendBatch(array $emails): array
    {
        $results = [];
        foreach ($emails as $email) {
            $results[] = $this->send($email);
        }
        return $results;
    }

    public function getStatus(string $messageId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->get("{$this->baseUrl}/emails/{$messageId}");

            if ($response->failed()) {
                throw new \Exception("Failed to get status: {$response->body()}");
            }

            return [
                'success' => true,
                'status' => $response->json('last_event'),
                'data' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get email status', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verify(string $email): bool
    {
        // Resend domain verification logic
        // This is typically done via dashboard, not API
        return true;
    }

    private function formatAttachments(array $attachments): array
    {
        return array_map(function ($attachment) {
            return [
                'filename' => $attachment['name'],
                'content' => base64_encode(file_get_contents($attachment['path'])),
            ];
        }, $attachments);
    }
}
```

---

### 3. SMTP Fallback Implementation

```php
// app/Services/Email/SmtpEmailService.php
namespace App\Services\Email;

use App\Contracts\EmailServiceInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class SmtpEmailService implements EmailServiceInterface
{
    public function send(array $params): array
    {
        try {
            Mail::html($params['html'], function (Message $message) use ($params) {
                $message->to($params['to'])
                        ->subject($params['subject']);

                if (isset($params['from'])) {
                    $message->from($params['from'], $params['fromName'] ?? '');
                }

                if (isset($params['cc'])) {
                    $message->cc($params['cc']);
                }

                if (isset($params['bcc'])) {
                    $message->bcc($params['bcc']);
                }

                if (isset($params['attachments'])) {
                    foreach ($params['attachments'] as $attachment) {
                        $message->attach($attachment['path'], [
                            'as' => $attachment['name'],
                        ]);
                    }
                }
            });

            return [
                'success' => true,
                'message_id' => uniqid('smtp_'),
                'provider' => 'smtp',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'smtp',
            ];
        }
    }

    public function sendBatch(array $emails): array
    {
        $results = [];
        foreach ($emails as $email) {
            $results[] = $this->send($email);
        }
        return $results;
    }

    public function getStatus(string $messageId): array
    {
        // SMTP doesn't provide tracking by default
        return [
            'success' => true,
            'status' => 'sent',
            'note' => 'SMTP does not provide delivery tracking',
        ];
    }

    public function verify(string $email): bool
    {
        return true;
    }
}
```

---

### 4. Service Provider Binding

```php
// app/Providers/NotificationServiceProvider.php
namespace App\Providers;

use App\Contracts\EmailServiceInterface;
use App\Services\Email\ResendEmailService;
use App\Services\Email\SmtpEmailService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind email service based on config
        $this->app->bind(EmailServiceInterface::class, function ($app) {
            $driver = config('services.email.driver', 'resend');

            return match ($driver) {
                'resend' => new ResendEmailService(),
                'smtp' => new SmtpEmailService(),
                // Easy to add more: 'ses' => new SesEmailService(),
                default => new ResendEmailService(),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
```

---

### 5. Custom Email Channel

```php
// app/Notifications/Channels/CustomEmailChannel.php
namespace App\Notifications\Channels;

use App\Contracts\EmailServiceInterface;
use Illuminate\Notifications\Notification;

class CustomEmailChannel
{
    public function __construct(
        private EmailServiceInterface $emailService
    ) {}

    public function send($notifiable, Notification $notification): void
    {
        // Get email content from notification
        $message = $notification->toMail($notifiable);

        // Convert to array format for our abstraction
        $params = [
            'to' => $notifiable->email,
            'subject' => $message->subject,
            'html' => $message->render(),
            'from' => config('mail.from.address'),
            'fromName' => config('mail.from.name'),
        ];

        // Send via abstracted service
        $result = $this->emailService->send($params);

        // Log result
        if (!$result['success']) {
            \Log::error('Email notification failed', [
                'notification' => get_class($notification),
                'recipient' => $notifiable->email,
                'error' => $result['error'],
            ]);
        }
    }
}
```

---

## 📱 Push Notification Strategy

### 1. OneSignal Integration

#### OneSignal Service

```php
// app/Services/Push/OneSignalService.php
namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    private string $appId;
    private string $apiKey;
    private string $baseUrl = 'https://onesignal.com/api/v1';

    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->apiKey = config('services.onesignal.api_key');
    }

    /**
     * Send push notification to specific users
     */
    public function sendToUsers(array $userIds, array $content): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'include_external_user_ids' => $userIds,
                'headings' => ['en' => $content['title']],
                'contents' => ['en' => $content['body']],
            ];

            // Optional parameters
            if (isset($content['data'])) {
                $payload['data'] = $content['data'];
            }
            if (isset($content['url'])) {
                $payload['url'] = $content['url'];
            }
            if (isset($content['icon'])) {
                $payload['small_icon'] = $content['icon'];
                $payload['large_icon'] = $content['icon'];
            }
            if (isset($content['image'])) {
                $payload['big_picture'] = $content['image'];
            }
            if (isset($content['buttons'])) {
                $payload['buttons'] = $content['buttons'];
            }

            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/notifications", $payload);

            if ($response->failed()) {
                throw new \Exception("OneSignal API error: {$response->body()}");
            }

            return [
                'success' => true,
                'notification_id' => $response->json('id'),
                'recipients' => $response->json('recipients'),
            ];

        } catch (\Exception $e) {
            Log::error('OneSignal push failed', [
                'error' => $e->getMessage(),
                'users' => $userIds,
                'content' => $content,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send to users with specific tags/segments
     */
    public function sendToSegment(string $segment, array $content): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'included_segments' => [$segment],
                'headings' => ['en' => $content['title']],
                'contents' => ['en' => $content['body']],
            ];

            // Add optional parameters like above
            if (isset($content['data'])) {
                $payload['data'] = $content['data'];
            }
            if (isset($content['url'])) {
                $payload['url'] = $content['url'];
            }

            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/notifications", $payload);

            if ($response->failed()) {
                throw new \Exception("OneSignal API error: {$response->body()}");
            }

            return [
                'success' => true,
                'notification_id' => $response->json('id'),
                'recipients' => $response->json('recipients'),
            ];

        } catch (\Exception $e) {
            Log::error('OneSignal segment push failed', [
                'error' => $e->getMessage(),
                'segment' => $segment,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Register user device
     */
    public function registerDevice(int $userId, string $deviceToken, string $deviceType = 'web'): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'device_type' => $this->getDeviceTypeId($deviceType),
                'identifier' => $deviceToken,
                'external_user_id' => (string) $userId,
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/players", $payload);

            if ($response->failed()) {
                throw new \Exception("Device registration failed: {$response->body()}");
            }

            return [
                'success' => true,
                'player_id' => $response->json('id'),
            ];

        } catch (\Exception $e) {
            Log::error('OneSignal device registration failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get notification status
     */
    public function getNotificationStatus(string $notificationId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->apiKey}",
            ])->get("{$this->baseUrl}/notifications/{$notificationId}?app_id={$this->appId}");

            if ($response->failed()) {
                throw new \Exception("Failed to get status: {$response->body()}");
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getDeviceTypeId(string $deviceType): int
    {
        return match ($deviceType) {
            'ios' => 0,
            'android' => 1,
            'amazon' => 2,
            'web' => 5,
            default => 5,
        };
    }
}
```

---

### 2. Custom Push Notification Channel

```php
// app/Notifications/Channels/PushNotificationChannel.php
namespace App\Notifications\Channels;

use App\Services\Push\OneSignalService;
use Illuminate\Notifications\Notification;

class PushNotificationChannel
{
    public function __construct(
        private OneSignalService $oneSignal
    ) {}

    public function send($notifiable, Notification $notification): void
    {
        // Check if notification has toPush method
        if (!method_exists($notification, 'toPush')) {
            return;
        }

        // Get push content from notification
        $content = $notification->toPush($notifiable);

        if (empty($content)) {
            return;
        }

        // Get user's OneSignal external ID (typically user ID)
        $userId = $notifiable->id;

        // Send push notification
        $result = $this->oneSignal->sendToUsers(
            [(string) $userId],
            $content
        );

        // Log result
        if (!$result['success']) {
            \Log::error('Push notification failed', [
                'notification' => get_class($notification),
                'user_id' => $userId,
                'error' => $result['error'],
            ]);
        }
    }
}
```

---

## 🎯 Unified Notification System

### 1. Base Notification Class

```php
// app/Notifications/BaseNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Determine notification channels based on user preferences
     */
    public function via($notifiable): array
    {
        $channels = $this->getDefaultChannels();

        // Filter by global config
        $channels = $this->filterByGlobalConfig($channels);

        // Filter by user preferences
        $channels = $this->filterByUserPreferences($notifiable, $channels);

        return $channels;
    }

    /**
     * Default channels for this notification type
     */
    abstract protected function getDefaultChannels(): array;

    /**
     * Get notification type identifier (for user preferences)
     */
    abstract protected function getNotificationType(): string;

    protected function filterByGlobalConfig(array $channels): array
    {
        return array_filter($channels, function ($channel) {
            return match ($channel) {
                'mail' => config('notifications.channels.email', true),
                'database' => config('notifications.channels.database', true),
                'push' => config('notifications.channels.push', true),
                default => true,
            };
        });
    }

    protected function filterByUserPreferences($notifiable, array $channels): array
    {
        $preference = $notifiable->notificationPreferences()
            ->where('notification_type', $this->getNotificationType())
            ->first();

        if (!$preference) {
            return $channels; // Use default if no preference set
        }

        return array_filter($channels, function ($channel) use ($preference) {
            return match ($channel) {
                'mail' => $preference->email_enabled,
                'database' => $preference->database_enabled,
                'push' => $preference->push_enabled,
                default => true,
            };
        });
    }

    /**
     * Get the array representation for database channel
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => $this->getNotificationType(),
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'data' => $this->getData(),
            'url' => $this->getUrl(),
        ];
    }

    // These methods can be overridden by child classes
    abstract protected function getTitle(): string;
    abstract protected function getMessage(): string;
    protected function getData(): array { return []; }
    protected function getUrl(): ?string { return null; }
}
```

---

### 2. Example: Approval Required Notification

```php
// app/Notifications/Approval/ApprovalRequiredNotification.php
namespace App\Notifications\Approval;

use App\Models\Approval;
use App\Notifications\BaseNotification;
use App\Notifications\Channels\CustomEmailChannel;
use App\Notifications\Channels\PushNotificationChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequiredNotification extends BaseNotification
{
    public function __construct(
        public readonly Approval $approval,
        public readonly Model $document // PR or PO
    ) {}

    protected function getDefaultChannels(): array
    {
        return [
            CustomEmailChannel::class,
            PushNotificationChannel::class,
            'database',
        ];
    }

    protected function getNotificationType(): string
    {
        return 'approval_required';
    }

    /**
     * Email representation
     */
    public function toMail($notifiable): MailMessage
    {
        $documentType = $this->getDocumentType();
        $documentNumber = $this->getDocumentNumber();
        $stepName = $this->approval->step->step_name;
        $url = $this->getUrl();

        return (new MailMessage)
            ->subject("🔔 Approval Required: {$documentType} {$documentNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new document requires your approval.")
            ->line("**Document:** {$documentType} {$documentNumber}")
            ->line("**Step:** {$stepName}")
            ->line("**Amount:** " . $this->getDocumentAmount())
            ->line("**Submitted by:** " . $this->document->createdBy->name)
            ->action('Review & Approve', $url)
            ->line('Please review this document at your earliest convenience.')
            ->salutation('Best regards, SCM Mining System');
    }

    /**
     * Push notification representation
     */
    public function toPush($notifiable): array
    {
        $documentType = $this->getDocumentType();
        $documentNumber = $this->getDocumentNumber();

        return [
            'title' => 'Approval Required',
            'body' => "{$documentType} {$documentNumber} needs your approval",
            'icon' => 'notification_icon',
            'url' => $this->getUrl(),
            'data' => [
                'type' => 'approval_required',
                'approval_id' => $this->approval->id,
                'document_id' => $this->document->id,
                'document_type' => get_class($this->document),
            ],
            'buttons' => [
                [
                    'id' => 'review',
                    'text' => 'Review Now',
                    'url' => $this->getUrl(),
                ],
            ],
        ];
    }

    // Inherited from BaseNotification
    protected function getTitle(): string
    {
        return 'Approval Required';
    }

    protected function getMessage(): string
    {
        $documentType = $this->getDocumentType();
        $documentNumber = $this->getDocumentNumber();
        return "{$documentType} {$documentNumber} requires your approval";
    }

    protected function getData(): array
    {
        return [
            'approval_id' => $this->approval->id,
            'document_type' => get_class($this->document),
            'document_id' => $this->document->id,
            'document_number' => $this->getDocumentNumber(),
            'step_name' => $this->approval->step->step_name,
            'amount' => $this->getDocumentAmount(),
        ];
    }

    protected function getUrl(): string
    {
        if ($this->document instanceof \App\Models\PurchaseRequest) {
            return url("/purchase-requests/{$this->document->id}");
        }
        return url("/purchase-orders/{$this->document->id}");
    }

    private function getDocumentType(): string
    {
        return $this->document instanceof \App\Models\PurchaseRequest
            ? 'Purchase Request'
            : 'Purchase Order';
    }

    private function getDocumentNumber(): string
    {
        return $this->document->pr_number ?? $this->document->po_number;
    }

    private function getDocumentAmount(): string
    {
        if ($this->document instanceof \App\Models\PurchaseOrder) {
            return number_format($this->document->grand_total, 2) . ' ' . $this->document->currency_code;
        }
        return 'N/A';
    }
}
```

---

## ⚙️ Configuration

### 1. Environment Variables

```bash
# .env

# ==========================================
# EMAIL CONFIGURATION
# ==========================================

# Email driver: resend, smtp, ses
EMAIL_DRIVER=resend

# Resend Configuration
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxx

# SMTP Configuration (fallback)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

# Email defaults
MAIL_FROM_ADDRESS=noreply@scm-mining.com
MAIL_FROM_NAME="SCM Mining System"

# ==========================================
# PUSH NOTIFICATION CONFIGURATION
# ==========================================

# OneSignal
ONESIGNAL_APP_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
ONESIGNAL_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# ==========================================
# NOTIFICATION SETTINGS
# ==========================================

# Channel toggles
NOTIFICATION_EMAIL_ENABLED=true
NOTIFICATION_DATABASE_ENABLED=true
NOTIFICATION_PUSH_ENABLED=true

# Queue settings
NOTIFICATION_QUEUE_ENABLED=true
NOTIFICATION_QUEUE_CONNECTION=database
NOTIFICATION_QUEUE_NAME=notifications

# Approval reminders
APPROVAL_REMINDER_ENABLED=true
APPROVAL_REMINDER_DAYS=2

# Stock alerts
STOCK_ALERT_ENABLED=true
STOCK_ALERT_THRESHOLD=20
```

---

### 2. Configuration Files

```php
// config/services.php
return [
    // ...existing services

    'email' => [
        'driver' => env('EMAIL_DRIVER', 'resend'),
    ],

    'resend' => [
        'api_key' => env('RESEND_API_KEY'),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'api_key' => env('ONESIGNAL_API_KEY'),
        'user_auth_key' => env('ONESIGNAL_USER_AUTH_KEY'), // Optional
    ],
];
```

```php
// config/notifications.php
return [
    'channels' => [
        'email' => env('NOTIFICATION_EMAIL_ENABLED', true),
        'database' => env('NOTIFICATION_DATABASE_ENABLED', true),
        'push' => env('NOTIFICATION_PUSH_ENABLED', true),
    ],

    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
        'queue' => env('NOTIFICATION_QUEUE_NAME', 'notifications'),
    ],

    'approval_reminder' => [
        'enabled' => env('APPROVAL_REMINDER_ENABLED', true),
        'days_threshold' => env('APPROVAL_REMINDER_DAYS', 2),
        'schedule' => 'daily', // cron schedule
    ],

    'stock_alert' => [
        'enabled' => env('STOCK_ALERT_ENABLED', true),
        'threshold_percentage' => env('STOCK_ALERT_THRESHOLD', 20),
    ],

    // Email provider fallback order
    'email_fallback' => [
        'primary' => 'resend',
        'fallback' => 'smtp',
    ],
];
```

---

## 📊 Database Schema

```sql
-- notifications table (Laravel default)
CREATE TABLE notifications (
    id UUID PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT NOT NULL,
    data JSONB NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read_at (read_at)
);

-- notification_preferences table
CREATE TABLE notification_preferences (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    email_enabled BOOLEAN DEFAULT true,
    database_enabled BOOLEAN DEFAULT true,
    push_enabled BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, notification_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- notification_logs table (for audit & debugging)
CREATE TABLE notification_logs (
    id BIGSERIAL PRIMARY KEY,
    notification_id UUID,
    user_id BIGINT NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    channel VARCHAR(50) NOT NULL, -- email, push, database
    status VARCHAR(50) NOT NULL, -- sent, failed, pending
    provider VARCHAR(50), -- resend, smtp, onesignal
    provider_message_id VARCHAR(255),
    error_message TEXT,
    payload JSONB,
    sent_at TIMESTAMP,
    created_at TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (notification_type),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- user_devices table (for push notifications)
CREATE TABLE user_devices (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    device_token VARCHAR(500) NOT NULL,
    device_type VARCHAR(20) NOT NULL, -- web, ios, android
    onesignal_player_id VARCHAR(255),
    is_active BOOLEAN DEFAULT true,
    last_active_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, device_token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## 🚀 Implementation Plan

### Phase 1: Foundation (2-3 hours)

**Step 1.1: Database Setup**

- [ ] Run migrations for notifications, preferences, logs, devices
- [ ] Create seeders for default preferences

**Step 1.2: Service Provider**

- [ ] Create NotificationServiceProvider
- [ ] Register email service binding
- [ ] Register notification channels

**Step 1.3: Configuration**

- [ ] Add Resend API key to .env
- [ ] Add OneSignal credentials to .env
- [ ] Create config files

---

### Phase 2: Email Service (2-3 hours)

**Step 2.1: Email Abstraction**

- [ ] Create EmailServiceInterface
- [ ] Implement ResendEmailService
- [ ] Implement SmtpEmailService (fallback)
- [ ] Create CustomEmailChannel

**Step 2.2: Email Templates**

- [ ] Create base email layout (Blade)
- [ ] Create approval email templates
- [ ] Create status change templates
- [ ] Test email rendering

---

### Phase 3: Push Notifications (2-3 hours)

**Step 3.1: OneSignal Integration**

- [ ] Create OneSignalService
- [ ] Implement device registration
- [ ] Implement push sending
- [ ] Create PushNotificationChannel

**Step 3.2: Frontend SDK**

- [ ] Add OneSignal JavaScript SDK
- [ ] Initialize OneSignal on app load
- [ ] Handle permission requests
- [ ] Register device tokens

---

### Phase 4: Unified Notifications (2-3 hours)

**Step 4.1: Base Classes**

- [ ] Create BaseNotification
- [ ] Implement channel filtering
- [ ] Implement user preferences check

**Step 4.2: Notification Classes**

- [ ] ApprovalRequiredNotification
- [ ] DocumentApprovedNotification
- [ ] DocumentRejectedNotification
- [ ] PendingApprovalReminderNotification

---

### Phase 5: Integration (1-2 hours)

**Step 5.1: Service Integration**

- [ ] Add triggers to ApprovalWorkflowService
- [ ] Add triggers to PurchaseRequestService
- [ ] Add triggers to PurchaseOrderService

**Step 5.2: Scheduled Commands**

- [ ] Create SendApprovalReminders command
- [ ] Create SendLowStockAlerts command
- [ ] Register in Kernel

---

### Phase 6: User Preferences UI (1-2 hours)

**Step 6.1: Backend**

- [ ] Create NotificationPreferenceController
- [ ] Create API endpoints

**Step 6.2: Frontend**

- [ ] Create preferences page
- [ ] Add toggle switches per notification type
- [ ] Save preferences

---

### Phase 7: Testing & Polish (1-2 hours)

- [ ] Unit tests for email service
- [ ] Unit tests for push service
- [ ] Feature tests for notifications
- [ ] Test all notification types
- [ ] Test user preferences
- [ ] Load testing

---

## 🧪 Testing Strategy

### 1. Manual Testing

```bash
# Test Resend email
php artisan tinker
>>> $user = User::first();
>>> $user->notify(new \App\Notifications\Approval\ApprovalRequiredNotification($approval, $document));

# Test push notification
>>> $oneSignal = app(\App\Services\Push\OneSignalService::class);
>>> $oneSignal->sendToUsers([1], ['title' => 'Test', 'body' => 'Hello']);

# Test scheduled commands
php artisan approvals:send-reminders
php artisan inventory:low-stock-alerts
```

### 2. Automated Tests

```php
// tests/Feature/Notifications/EmailNotificationTest.php
test('sends email via resend service', function () {
    Mail::fake();

    $user = User::factory()->create();
    $approval = Approval::factory()->create();
    $document = PurchaseOrder::factory()->create();

    $user->notify(new ApprovalRequiredNotification($approval, $document));

    Mail::assertSent(function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

// tests/Feature/Notifications/PushNotificationTest.php
test('sends push notification via onesignal', function () {
    Http::fake([
        'onesignal.com/*' => Http::response([
            'id' => 'test-notification-id',
            'recipients' => 1,
        ], 200),
    ]);

    $user = User::factory()->create();
    $oneSignal = app(\App\Services\Push\OneSignalService::class);

    $result = $oneSignal->sendToUsers([$user->id], [
        'title' => 'Test',
        'body' => 'Test notification',
    ]);

    expect($result['success'])->toBeTrue();
});
```

---

## 🔄 Migration Path

### Switching Email Providers

**From Resend to SMTP:**

```bash
# 1. Update .env
EMAIL_DRIVER=smtp
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
# ... other SMTP settings

# 2. Clear config cache
php artisan config:clear

# 3. Test
php artisan tinker
>>> Mail::raw('Test', fn($msg) => $msg->to('test@example.com')->subject('Test'));
```

**From Resend to AWS SES:**

```php
// 1. Create SES service (future)
// app/Services/Email/SesEmailService.php
class SesEmailService implements EmailServiceInterface { }

// 2. Update service provider binding
$this->app->bind(EmailServiceInterface::class, function ($app) {
    return match (config('services.email.driver')) {
        'resend' => new ResendEmailService(),
        'smtp' => new SmtpEmailService(),
        'ses' => new SesEmailService(), // ← New
        default => new ResendEmailService(),
    };
});

// 3. Update .env
EMAIL_DRIVER=ses
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1
```

---

## 📈 Success Metrics

### Delivery Metrics

- ✅ Email delivery rate > 98%
- ✅ Push notification delivery rate > 95%
- ✅ Average email delivery time < 30 seconds
- ✅ Average push delivery time < 5 seconds

### User Engagement

- ✅ Email open rate > 60%
- ✅ Push notification click rate > 40%
- ✅ Notification preferences customization rate > 30%

### System Performance

- ✅ Queue processing time < 1 minute
- ✅ No notification failures due to system errors
- ✅ Proper fallback handling

---

## 🎯 Decision Points

**Before starting implementation:**

1. **Resend Setup**
    - [ ] Create Resend account
    - [ ] Verify domain for sending
    - [ ] Get API key
    - [ ] Test sending from verified domain

2. **OneSignal Setup**
    - [ ] Create OneSignal account
    - [ ] Create app for web push
    - [ ] Get App ID and API Key
    - [ ] Configure web push settings

3. **Infrastructure**
    - [ ] Decide queue driver (database vs Redis)
    - [ ] Setup queue worker for production
    - [ ] Configure supervisor for queue workers

4. **User Devices**
    - [ ] Decide when to prompt for push permission
    - [ ] Design permission request UI
    - [ ] Handle permission denial gracefully

5. **Testing**
    - [ ] Setup test email addresses
    - [ ] Create test OneSignal accounts
    - [ ] Define testing checklist

---

## 📚 Related Documentation

- [Resend API Documentation](https://resend.com/docs)
- [OneSignal Web Push Documentation](https://documentation.onesignal.com/docs/web-push-quickstart)
- [Laravel Notifications Documentation](https://laravel.com/docs/notifications)

---

**Status:** 📋 **READY FOR IMPLEMENTATION**

This document provides a complete blueprint for implementing a unified, elegant, and maintainable notification system with easy migration paths.
