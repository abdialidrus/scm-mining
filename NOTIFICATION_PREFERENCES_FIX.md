# Notification Preferences API Fix

## Issue

When accessing `/api/notification-preferences`, the API threw a database constraint error:

```
SQLSTATE[23502]: Not null violation: 7 ERROR: null value in column "notification_type"
of relation "notification_preferences" violates not-null constraint
```

## Root Cause

There was a mismatch between the database schema and the controller implementation:

### Database Schema (Multiple Rows Approach)

```sql
CREATE TABLE notification_preferences (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    notification_type VARCHAR NOT NULL,  -- Each type gets its own row
    email_enabled BOOLEAN DEFAULT true,
    push_enabled BOOLEAN DEFAULT true,
    database_enabled BOOLEAN DEFAULT true,
    UNIQUE(user_id, notification_type)
);
```

### Original Controller (Single Row Approach)

The controller was trying to create a single row with JSON preferences:

```php
NotificationPreference::firstOrCreate(
    ['user_id' => $user->id],  // ❌ Missing notification_type
    [
        'email_enabled' => true,
        'preferences' => [...],  // ❌ JSON approach
    ]
);
```

## Solution

### Updated Controller Implementation

#### 1. `index()` Method - Get Preferences

**Before:** Single row with JSON

```php
$preference = NotificationPreference::firstOrCreate(
    ['user_id' => $user->id],
    ['preferences' => $this->getDefaultPreferences()]
);
```

**After:** Multiple rows, one per type

```php
$preferences = NotificationPreference::where('user_id', $user->id)->get();

if ($preferences->isEmpty()) {
    $this->createDefaultPreferences($user->id);
    $preferences = NotificationPreference::where('user_id', $user->id)->get();
}

// Format for frontend
$formattedPreferences = [];
foreach ($preferences as $pref) {
    $formattedPreferences[$pref->notification_type] = [
        'email' => $pref->email_enabled,
        'push' => $pref->push_enabled,
        'database' => $pref->database_enabled,
    ];
}

// Calculate global toggles
$globalPreferences = [
    'email' => $preferences->where('email_enabled', true)->isNotEmpty(),
    'push' => $preferences->where('push_enabled', true)->isNotEmpty(),
    'database' => $preferences->where('database_enabled', true)->isNotEmpty(),
];
```

#### 2. `update()` Method - Save Preferences

**Before:** Update single row

```php
NotificationPreference::updateOrCreate(
    ['user_id' => $user->id],
    $validated
);
```

**After:** Update multiple rows

```php
foreach ($validated['preferences'] as $type => $channels) {
    NotificationPreference::updateOrCreate(
        [
            'user_id' => $user->id,
            'notification_type' => $type,  // ✅ Include type
        ],
        [
            'email_enabled' => $channels['email'],
            'push_enabled' => $channels['push'],
            'database_enabled' => $channels['database'],
        ]
    );
}
```

#### 3. `reset()` Method - Reset to Defaults

**Before:** Update single row

```php
NotificationPreference::updateOrCreate(
    ['user_id' => $user->id],
    ['preferences' => $this->getDefaultPreferences()]
);
```

**After:** Delete and recreate

```php
NotificationPreference::where('user_id', $user->id)->delete();
$this->createDefaultPreferences($user->id);
```

#### 4. New Method: `createDefaultPreferences()`

```php
private function createDefaultPreferences(int $userId): void
{
    $defaults = $this->getDefaultPreferences();

    foreach ($defaults as $type => $channels) {
        NotificationPreference::create([
            'user_id' => $userId,
            'notification_type' => $type,
            'email_enabled' => $channels['email'],
            'push_enabled' => $channels['push'],
            'database_enabled' => $channels['database'],
        ]);
    }
}
```

## Database Structure

### Before vs After

**Single Row Approach (❌ Wrong)**

```
| id | user_id | email_enabled | preferences (JSON)        |
|----|---------|---------------|---------------------------|
| 1  | 6       | true          | {"approval_required": {}} |
```

**Multiple Rows Approach (✅ Correct)**

```
| id | user_id | notification_type   | email | push | database |
|----|---------|---------------------|-------|------|----------|
| 1  | 6       | approval_required   | true  | true | true     |
| 2  | 6       | document_approved   | true  | true | true     |
| 3  | 6       | document_rejected   | true  | true | true     |
| 4  | 6       | approval_reminder   | true  | true | true     |
| 5  | 6       | low_stock_alert     | true  | false| true     |
```

## API Response Structure

### GET /api/notification-preferences

**Before:**

```json
{
    "preferences": {
        "id": 1,
        "user_id": 6,
        "email_enabled": true,
        "preferences": {...}
    }
}
```

**After:**

```json
{
    "global_preferences": {
        "email": true,
        "push": true,
        "database": true
    },
    "preferences": {
        "approval_required": {
            "email": true,
            "push": true,
            "database": true
        },
        "document_approved": {
            "email": true,
            "push": true,
            "database": true
        }
        // ... more types
    },
    "notification_types": [
        {
            "key": "approval_required",
            "name": "Approval Required",
            "description": "When a document is assigned to you for approval",
            "category": "Approvals"
        }
        // ... more types
    ]
}
```

## Default Preferences

Each user gets 5 notification types by default:

| Type              | Email | Push | Database |
| ----------------- | ----- | ---- | -------- |
| approval_required | ✓     | ✓    | ✓        |
| document_approved | ✓     | ✓    | ✓        |
| document_rejected | ✓     | ✓    | ✓        |
| approval_reminder | ✓     | ✓    | ✓        |
| low_stock_alert   | ✓     | ✗    | ✓        |

**Note:** `low_stock_alert` has push disabled by default since it's informational.

## Testing

### Created Test Data

- **40 total preferences** (8 users × 5 notification types)
- All users now have default preferences

### Verification

```bash
php artisan tinker
> NotificationPreference::count()
=> 40

> NotificationPreference::where('user_id', 6)->count()
=> 5

> NotificationPreference::where('user_id', 6)->get(['notification_type', 'email_enabled'])
```

## Files Modified

1. **app/Http/Controllers/NotificationPreferenceController.php**
    - `index()` - Fetch multiple rows, format for frontend
    - `update()` - Update multiple rows
    - `reset()` - Delete and recreate
    - `createDefaultPreferences()` - New helper method

## Frontend Compatibility

The Vue component `NotificationPreferences.vue` already expects this structure:

```typescript
preferences: {
  approval_required: { email: true, push: true, database: true },
  document_approved: { email: true, push: true, database: true },
  // ...
}
```

No frontend changes needed! ✅

## Status

✅ **FIXED** - API now works correctly with multiple rows per user
✅ Default preferences created for all users
✅ Frontend compatible with new API structure
✅ Database constraints satisfied

## Next Steps

Navigate to **http://localhost:8000/notifications/preferences** to test:

1. View current preferences
2. Toggle global email/push/database
3. Toggle per-notification-type preferences
4. Save changes
5. Reset to defaults
