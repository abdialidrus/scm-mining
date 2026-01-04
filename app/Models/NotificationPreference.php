<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type',
        'email_enabled',
        'database_enabled',
        'push_enabled',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'database_enabled' => 'boolean',
        'push_enabled' => 'boolean',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get user's preference for a notification type and channel.
     */
    public static function isEnabled(int $userId, string $notificationType, string $channel): bool
    {
        $preference = static::where('user_id', $userId)
            ->where('notification_type', $notificationType)
            ->first();

        if (!$preference) {
            // Default to enabled if no preference set
            return true;
        }

        return match ($channel) {
            'mail' => $preference->email_enabled,
            'database' => $preference->database_enabled,
            'push' => $preference->push_enabled,
            default => false,
        };
    }
}
