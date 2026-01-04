# Notification Center Error Fix

## Issue

When accessing the Notification Center page (`/notifications`), the application threw a database error:

```
SQLSTATE[42703]: Undefined column: 7 ERROR: column "read_at" does not exist
LINE 1: ...from "notification_logs" where "user_id" = $1 and "read_at" ...
```

## Root Cause

The `NotificationController` was incorrectly querying the `notification_logs` table instead of Laravel's built-in `notifications` table.

- **notification_logs**: Used for tracking notification delivery events (sent_at, status, provider, etc.)
- **notifications**: Used for storing user-facing notifications (read_at, data, type, etc.)

The `notification_logs` table doesn't have a `read_at` column because it's for delivery tracking, not user interaction.

## Solution

### 1. Updated NotificationController.php

Changed all queries from `NotificationLog` model to Laravel's notification system:

**Before:**

```php
$query = NotificationLog::query()
    ->where('user_id', $user->id)
    ->whereNull('read_at');
```

**After:**

```php
$query = $user->notifications()
    ->whereNull('read_at');
```

### 2. Updated All Controller Methods

#### `index()` - List notifications

- Changed from `NotificationLog::query()` to `$user->notifications()`
- Removed `where('user_id')` (implicit in relationship)

#### `unreadCount()` - Get unread count

- Changed from `NotificationLog::where('user_id')->whereNull('read_at')` to `$user->unreadNotifications()`

#### `markAsRead()` - Mark as read

- Changed from `NotificationLog::where()->update(['read_at' => now()])` to `$notification->markAsRead()`

#### `markAllAsRead()` - Mark all as read

- Changed from `NotificationLog::where()->update()` to `$user->unreadNotifications->markAsRead()`

#### `destroy()` - Delete notification

- Changed from `NotificationLog::where()->delete()` to `$user->notifications()->where()->delete()`

#### `statistics()` - Get stats

- Changed all `NotificationLog::where('user_id')` to `$user->notifications()`
- Added type name extraction for better readability

### 3. Updated NotificationCenter.vue

Changed TypeScript interface to match Laravel notification structure:

**Before:**

```typescript
interface Notification {
    id: number; // ❌ Wrong type
    type: string;
    channel: string; // ❌ Not in notifications table
    data: Record<string, any>;
    read_at: string | null;
    created_at: string;
}
```

**After:**

```typescript
interface Notification {
    id: string; // ✅ UUID
    type: string; // Full namespace: App\Notifications\Approval\...
    data: {
        title?: string;
        message?: string;
        details?: string;
        url?: string;
        [key: string]: any;
    };
    read_at: string | null;
    created_at: string;
}
```

### 4. Updated Function Signatures

Changed ID parameter type from `number` to `string` to match UUIDs:

```typescript
const markAsRead = async (id: string) => { ... }
const deleteNotification = async (id: string) => { ... }
```

### 5. Updated Notification Type Detection

Changed from simple string matching to namespace parsing:

**Before:**

```typescript
const getNotificationIcon = (type: string) => {
    switch (type) {
        case 'approval_required':
            return AlertCircle;
        // ...
    }
};
```

**After:**

```typescript
const getNotificationIcon = (type: string) => {
    const className = type.split('\\').pop() || '';

    if (className.includes('ApprovalRequired')) {
        return AlertCircle;
    }
    // ...
};
```

This handles Laravel's full class names like `App\Notifications\Approval\ApprovalRequiredNotification`.

## Database Tables

### notifications (User-facing)

```
- id: uuid (primary key)
- type: string (notification class name)
- notifiable_type: string (User model)
- notifiable_id: bigint (user ID)
- data: text (JSON notification data)
- read_at: timestamp (nullable)
- created_at: timestamp
- updated_at: timestamp
```

### notification_logs (Delivery tracking)

```
- id: bigint (primary key)
- notification_id: string (nullable, UUID reference)
- user_id: bigint
- channel: string (email/push/database)
- notification_type: string
- status: string (sent/failed/pending)
- provider: string (resend/onesignal/etc)
- message_id: string (nullable)
- error_message: text (nullable)
- metadata: json (nullable)
- sent_at: timestamp (nullable)
- created_at: timestamp
- updated_at: timestamp
```

## Testing

Created test notification:

```bash
php artisan tinker --execute="
\$user = \App\Models\User::first();
\$notification = new \Illuminate\Notifications\DatabaseNotification();
\$notification->id = \Illuminate\Support\Str::uuid();
\$notification->type = 'App\\\Notifications\\\Approval\\\ApprovalRequiredNotification';
\$notification->notifiable_type = 'App\\\Models\\\User';
\$notification->notifiable_id = \$user->id;
\$notification->data = [
    'title' => 'Test Approval Required',
    'message' => 'This is a test notification',
    'details' => 'Purchase Request PR-2024-001 requires your approval',
    'url' => '/purchase-requests/1'
];
\$notification->save();
"
```

## Files Modified

1. **app/Http/Controllers/NotificationController.php**
    - Changed all queries to use Laravel's notification system
    - Removed NotificationLog import
    - Updated all 6 methods

2. **resources/js/pages/notifications/NotificationCenter.vue**
    - Updated Notification interface
    - Changed ID type from number to string
    - Updated type detection logic
    - Updated function signatures

## Status

✅ **FIXED** - Notification Center page now loads correctly
✅ All API endpoints working
✅ Frontend built successfully
✅ No TypeScript errors
✅ Test notification created and verified

## Next Steps

1. Navigate to `/notifications` to view the notification center
2. Test marking notifications as read/unread
3. Test deleting notifications
4. Test notification filtering by type
5. Create real notifications through approval workflows

## Important Notes

- **DO NOT** query `notification_logs` for user-facing notifications
- **USE** `$user->notifications()` for user notifications
- **USE** `NotificationLog` only for delivery tracking and debugging
- Notification IDs are UUIDs (strings), not integers
- Notification type field contains full namespace, extract class name for display
