<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'notification_type',
        'status',
        'provider',
        'message_id',
        'error_message',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the user that owns the log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a notification event.
     */
    public static function logNotification(
        ?string $notificationId,
        int $userId,
        string $channel,
        string $notificationType,
        string $status,
        ?string $provider = null,
        ?string $messageId = null,
        ?string $errorMessage = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'channel' => $channel,
            'notification_type' => $notificationType,
            'status' => $status,
            'provider' => $provider,
            'message_id' => $messageId,
            'error_message' => $errorMessage,
            'metadata' => $metadata,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
