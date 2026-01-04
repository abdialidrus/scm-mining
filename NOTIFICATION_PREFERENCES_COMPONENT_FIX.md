# NotificationPreferences Vue Component Fix

## Issue

When loading the `/notifications/preferences` page, the component threw a JavaScript error:

```
NotificationPreferences.vue:309 Uncaught (in promise) TypeError:
Cannot read properties of undefined (reading 'email')
```

## Root Cause

The Vue component was trying to bind to `preferences[type.key].email` before the data was loaded from the API, resulting in `undefined` access errors.

**Timeline of the issue:**

1. Component mounts → `preferences.value = {}` (empty object)
2. Template renders → tries to access `preferences['approval_required'].email`
3. `preferences['approval_required']` is `undefined` → ❌ Error!
4. API responds → preferences populated → ✅ but too late

## Solution

### 1. Fixed API Response Mapping

**Before:**

```typescript
globalPreferences.value = {
    email_enabled: response.data.preferences.email_enabled, // ❌ Wrong path
    push_enabled: response.data.preferences.push_enabled,
    database_enabled: response.data.preferences.database_enabled,
};

preferences.value = response.data.preferences.preferences || {}; // ❌ Nested wrongly
```

**After:**

```typescript
globalPreferences.value = {
    email_enabled: response.data.global_preferences?.email ?? true, // ✅ Correct path
    push_enabled: response.data.global_preferences?.push ?? true,
    database_enabled: response.data.global_preferences?.database ?? true,
};

preferences.value = response.data.preferences || {}; // ✅ Direct access
```

### 2. Added Initialization for Missing Types

```typescript
// Initialize preferences for any missing notification types
notificationTypes.value.forEach((type) => {
    if (!preferences.value[type.key]) {
        preferences.value[type.key] = {
            email: true,
            push: true,
            database: true,
        };
    }
});
```

This ensures every notification type has a preference object, even if API returns incomplete data.

### 3. Fixed Reset Method

**Before:**

```typescript
const response = await axios.post('/api/notification-preferences/reset');

globalPreferences.value = {
    email_enabled: response.data.preferences.email_enabled, // ❌ Wrong
    // ...
};
```

**After:**

```typescript
await axios.post('/api/notification-preferences/reset');

// Re-fetch preferences after reset
await fetchPreferences(); // ✅ Reuse fetch logic
```

### 4. Added Template Guards

**Before:**

```vue
<Switch
    v-model:checked="preferences[type.key].email"
    <!-- ❌ Fails if preferences[type.key] is undefined -->
/>
```

**After:**

```vue
<div v-if="preferences[type.key]" class="flex items-center space-x-2">
    <Switch
        v-model:checked="preferences[type.key].email"  
        <!-- ✅ Only renders if preferences[type.key] exists -->
    />
</div>
```

**Note:** Cannot use optional chaining (`?.`) with `v-model` because it creates an invalid assignment target:

```vue
<!-- ❌ This doesn't work: -->
<Switch v-model:checked="preferences[type.key]?.email" />

<!-- ✅ Use v-if instead: -->
<div v-if="preferences[type.key]">
    <Switch v-model:checked="preferences[type.key].email" />
</div>
```

## Data Flow

### API Response Structure

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
        }
    },
    "notification_types": [
        {
            "key": "approval_required",
            "name": "Approval Required",
            "description": "...",
            "category": "Approvals"
        }
    ]
}
```

### Vue Component State

```typescript
globalPreferences.value = {
    email_enabled: true,
    push_enabled: true,
    database_enabled: true,
}

preferences.value = {
    approval_required: { email: true, push: true, database: true },
    document_approved: { email: true, push: true, database: true },
    document_rejected: { email: true, push: true, database: true },
    approval_reminder: { email: true, push: true, database: true },
    low_stock_alert: { email: true, push: false, database: true },
}

notificationTypes.value = [
    { key: 'approval_required', name: 'Approval Required', ... },
    // ... more types
]
```

## Key Changes Summary

| Aspect           | Before                                    | After                                    |
| ---------------- | ----------------------------------------- | ---------------------------------------- |
| API path         | `response.data.preferences.email_enabled` | `response.data.global_preferences.email` |
| Preferences path | `response.data.preferences.preferences`   | `response.data.preferences`              |
| Initialization   | None                                      | Loop through types, create defaults      |
| Reset method     | Parse response manually                   | Re-fetch with `fetchPreferences()`       |
| Template safety  | Direct access                             | `v-if` guards                            |

## Files Modified

1. **resources/js/pages/notifications/NotificationPreferences.vue**
    - `fetchPreferences()` - Fixed API response mapping, added initialization
    - `resetToDefaults()` - Simplified to re-fetch after reset
    - Template - Added `v-if="preferences[type.key]"` guards on all Switch components

## Testing

### Verification Steps

1. ✅ Navigate to `/notifications/preferences`
2. ✅ Page loads without console errors
3. ✅ All 5 notification types displayed with switches
4. ✅ Global toggles work
5. ✅ Per-type toggles work
6. ✅ Save button works
7. ✅ Reset button works

### Expected Behavior

- Page loads smoothly with loading spinner
- Switches render for all notification types
- All switches respond to clicks
- Saving preferences shows success toast
- Resetting preferences shows success toast and reloads data

## Status

✅ **FIXED** - Component now loads without errors
✅ All switches functional
✅ API integration working correctly
✅ Data initialization handled properly

## Related Fixes

This fix completes the notification preferences system alongside:

1. **NotificationController** - Database table fix (notifications vs notification_logs)
2. **Axios Configuration** - Session cookies fix (withCredentials)
3. **NotificationPreferenceController** - Multiple rows structure fix

## Complete Notification System Status

**100% Working! 🎉**

All notification features are now fully functional:

- ✅ Notification Center page
- ✅ Notification Preferences page
- ✅ API endpoints (10 total)
- ✅ Email notifications (Resend)
- ✅ Push notifications (OneSignal)
- ✅ Database notifications
- ✅ User preferences management
