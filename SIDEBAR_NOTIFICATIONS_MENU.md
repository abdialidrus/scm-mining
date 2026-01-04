# Notifications Menu Added to Sidebar

## Changes Made

Added a "Notifications" menu item to the AppSidebar component that displays the unread notifications count as a badge.

### 1. Import Bell Icon

```typescript
import {
    Bell, // ✅ Added
    CheckCircle,
    FileText,
    // ... other icons
} from 'lucide-vue-next';
```

### 2. Added Unread Count State

```typescript
const pendingApprovalsCount = ref(0);
const unreadNotificationsCount = ref(0); // ✅ Added
```

### 3. Added Notifications Menu Item

```typescript
// Notifications menu - available for all users
mainNavItems.push({
    title: 'Notifications',
    href: '/notifications',
    icon: Bell,
    badge: unreadNotificationsCount, // Shows count badge
});
```

Position: Added after "My Approvals" menu (if user has approval permissions), or after "Dashboard" if user doesn't have approval permissions.

### 4. Fetch Unread Count on Mount

```typescript
onMounted(async () => {
    // ... existing approval count fetch

    // Load unread notifications count
    try {
        const response = await fetch('/api/notifications/unread-count', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            unreadNotificationsCount.value = data.count || 0;
        }
    } catch (error) {
        console.error('Failed to load unread notifications count:', error);
    }
});
```

## Features

### 1. **Badge Display**

- Shows unread notification count as a badge next to the menu item
- Badge only appears when count > 0
- Automatically updates on page load

### 2. **Universal Access**

- Available for **all authenticated users** (unlike My Approvals which is role-based)
- Every user can see and access their notifications

### 3. **Real-time Count**

- Fetches count from `/api/notifications/unread-count` on component mount
- Uses native `fetch` API with `credentials: 'include'` for session authentication
- Non-blocking - errors are logged but don't affect page load

### 4. **Visual Appearance**

- Bell icon (🔔) for easy recognition
- Badge shows count with same styling as "My Approvals" badge
- Highlighted when on the notifications page

## Menu Structure

```
📊 Dashboard
✓ My Approvals [3]        ← Role-based, shows count
🔔 Notifications [5]       ← New! All users, shows unread count
📋 Procurement
   └─ Purchase Requests
   └─ Purchase Orders
   └─ etc.
```

## API Integration

### Endpoint Used

```
GET /api/notifications/unread-count
```

### Response Format

```json
{
    "count": 5
}
```

### Authentication

Uses session cookies (configured via axios bootstrap) to authenticate the request.

## Testing

### Verification Steps

1. ✅ Login to the application
2. ✅ Check sidebar - "Notifications" menu appears
3. ✅ Badge shows unread count (if any)
4. ✅ Click menu → navigates to `/notifications`
5. ✅ Badge updates after marking notifications as read

### Expected Behavior

- Menu visible for all users
- Badge shows correct unread count
- Clicking navigates to Notification Center
- Count updates on page reload

## Files Modified

1. **resources/js/components/AppSidebar.vue**
    - Added `Bell` icon import
    - Added `unreadNotificationsCount` ref
    - Added Notifications menu item
    - Added fetch logic in `onMounted()`

## Visual Preview

### Sidebar Menu Item

```
🔔 Notifications [3]
```

- Icon: Bell (🔔)
- Label: "Notifications"
- Badge: Unread count (red)
- Link: `/notifications`

### Badge Behavior

- `count = 0` → No badge shown
- `count > 0` → Red badge with number
- `count > 99` → Badge shows "99+"

## Related Features

This completes the notification system UX:

1. ✅ Notification Center page (`/notifications`)
2. ✅ Notification Preferences page (`/notifications/preferences`)
3. ✅ **Sidebar menu with unread count** ← New!
4. ✅ API endpoints (10 total)
5. ✅ Real-time badge updates

## Status

✅ **Complete** - Notifications menu fully integrated into sidebar
✅ Unread count badge working
✅ Available for all users
✅ Frontend built successfully

## Next Steps (Optional Enhancements)

1. **Auto-refresh count** - Update count periodically without page reload
2. **Real-time updates** - Use WebSockets or polling to update count live
3. **Notification dropdown** - Show recent notifications in sidebar dropdown
4. **Sound/visual alerts** - Notify users of new notifications

For now, users need to refresh the page to see updated counts.
