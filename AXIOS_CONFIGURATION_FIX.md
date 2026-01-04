# Axios Configuration Fix

## Issue

API calls from Vue components were returning empty data because axios was not configured to send session cookies with requests.

## Root Cause

Laravel Sanctum requires stateful authentication for SPA applications. This means:

1. The frontend must send cookies with API requests (`withCredentials: true`)
2. The CSRF token must be included in request headers
3. The `Accept: application/json` header should be set

Without these configurations, the API endpoints couldn't identify the authenticated user, resulting in empty data.

## Solution

### 1. Created Bootstrap File (`resources/js/bootstrap.ts`)

Created a centralized axios configuration file:

```typescript
import axios from 'axios';

// Configure axios to include credentials (cookies) with requests
axios.defaults.withCredentials = true;

// Set default headers
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Handle CSRF token from meta tag
const token = document.head.querySelector<HTMLMetaElement>(
    'meta[name="csrf-token"]',
);
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

export default axios;
```

### 2. Updated app.ts

Import bootstrap configuration at the top of `app.ts`:

```typescript
import './bootstrap';
```

### 3. Updated Vue Components

Changed axios imports to use the configured instance:

**Before:**

```typescript
import axios from 'axios';
```

**After:**

```typescript
import axios from '@/bootstrap';
```

Files updated:

- `resources/js/pages/notifications/NotificationCenter.vue`
- `resources/js/pages/notifications/NotificationPreferences.vue`

## Why This Works

### Without withCredentials

```
Browser                    Laravel API
  |                            |
  |--- GET /api/notifications --->
  |    (no cookies sent)       |
  |                            |
  |<-- 200 OK (empty data) ----| ❌ No user session
```

### With withCredentials

```
Browser                    Laravel API
  |                            |
  |--- GET /api/notifications --->
  |    Cookie: laravel_session |
  |    X-CSRF-TOKEN: xxx       |
  |                            |
  |<-- 200 OK (user data) -----| ✅ User authenticated
```

## Testing

### Before Fix

```json
{
    "current_page": 1,
    "data": [], // ❌ Empty
    "total": 0
}
```

### After Fix

```json
{
    "current_page": 1,
    "data": [
        {
            "id": "5ddc22ce-5bcb-402a-ba1e-584b1518f8f3",
            "type": "App\\Notifications\\Approval\\ApprovalRequiredNotification",
            "data": {
                "title": "Approval Required",
                "message": "Purchase Request PR-2024-001 requires your approval",
                "details": "Total Amount: Rp 5,000,000 - Submitted by: ENG Requester",
                "url": "/purchase-requests/1"
            },
            "read_at": null,
            "created_at": "2026-01-04T10:51:21.000000Z"
        }
        // ... more notifications
    ],
    "total": 4 // ✅ Data present
}
```

## Important Notes

1. **Always use configured axios**: Import from `@/bootstrap`, not `axios` package directly
2. **Sanctum stateful domains**: Already configured in `config/sanctum.php` to include `localhost`
3. **CSRF protection**: Automatically handled by the bootstrap file
4. **Session cookies**: Automatically sent with every request

## Test Notifications Created

For testing purposes, 4 notifications were created for each user (total 36):

1. **Approval Required** (unread) - Blue alert icon
2. **Document Approved** (read) - Green check icon
3. **Document Rejected** (unread) - Red X icon
4. **Pending Approvals Reminder** (unread) - Orange clock icon

## Verification

Navigate to **http://localhost:8000/notifications** to see:

- 4 notifications per user
- 3 unread notifications badge
- Color-coded icons by type
- "Mark all as read" functionality
- Delete individual notifications
- Filter by unread/all tabs

## Files Modified

1. **resources/js/bootstrap.ts** (NEW) - Axios configuration
2. **resources/js/app.ts** - Import bootstrap
3. **resources/js/pages/notifications/NotificationCenter.vue** - Use configured axios
4. **resources/js/pages/notifications/NotificationPreferences.vue** - Use configured axios

## Related Documentation

- See `NOTIFICATION_CENTER_FIX.md` for the database table fix
- See `NOTIFICATION_SYSTEM_COMPLETE.md` for complete system documentation
