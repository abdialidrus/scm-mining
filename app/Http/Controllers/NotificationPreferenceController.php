<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    /**
     * Get the authenticated user's notification preferences.
     */
    public function index()
    {
        $user = Auth::user();

        // Get existing preferences or create defaults
        $preferences = NotificationPreference::where('user_id', $user->id)->get();

        // If no preferences exist, create defaults
        if ($preferences->isEmpty()) {
            $this->createDefaultPreferences($user->id);
            $preferences = NotificationPreference::where('user_id', $user->id)->get();
        }

        // Format preferences for frontend
        $formattedPreferences = [];
        foreach ($preferences as $pref) {
            $formattedPreferences[$pref->notification_type] = [
                'email' => $pref->email_enabled,
                'push' => $pref->push_enabled,
                'database' => $pref->database_enabled,
            ];
        }

        // Calculate global preferences (all enabled if any type has it enabled)
        $globalPreferences = [
            'email' => $preferences->where('email_enabled', true)->isNotEmpty(),
            'push' => $preferences->where('push_enabled', true)->isNotEmpty(),
            'database' => $preferences->where('database_enabled', true)->isNotEmpty(),
        ];

        return response()->json([
            'global_preferences' => $globalPreferences,
            'preferences' => $formattedPreferences,
            'notification_types' => $this->getNotificationTypes(),
        ]);
    }

    /**
     * Update the authenticated user's notification preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'global_preferences' => 'sometimes|array',
            'global_preferences.email' => 'sometimes|boolean',
            'global_preferences.push' => 'sometimes|boolean',
            'global_preferences.database' => 'sometimes|boolean',
            'preferences' => 'sometimes|array',
            'preferences.*.email' => 'sometimes|boolean',
            'preferences.*.push' => 'sometimes|boolean',
            'preferences.*.database' => 'sometimes|boolean',
        ]);

        $user = Auth::user();

        // Update or create preferences for each type
        if (isset($validated['preferences'])) {
            foreach ($validated['preferences'] as $type => $channels) {
                NotificationPreference::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'notification_type' => $type,
                    ],
                    [
                        'email_enabled' => $channels['email'] ?? true,
                        'push_enabled' => $channels['push'] ?? true,
                        'database_enabled' => $channels['database'] ?? true,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Notification preferences updated successfully',
        ]);
    }

    /**
     * Get all available notification types.
     */
    public function types()
    {
        return response()->json([
            'types' => $this->getNotificationTypes(),
        ]);
    }

    /**
     * Reset preferences to defaults.
     */
    public function reset()
    {
        $user = Auth::user();

        // Delete all existing preferences
        NotificationPreference::where('user_id', $user->id)->delete();

        // Create default preferences
        $this->createDefaultPreferences($user->id);

        return response()->json([
            'message' => 'Notification preferences reset to defaults',
        ]);
    }

    /**
     * Create default notification preferences for a user.
     */
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

    /**
     * Get default notification preferences structure.
     */
    private function getDefaultPreferences(): array
    {
        return [
            'approval_required' => [
                'email' => true,
                'push' => true,
                'database' => true,
            ],
            'document_approved' => [
                'email' => true,
                'push' => true,
                'database' => true,
            ],
            'document_rejected' => [
                'email' => true,
                'push' => true,
                'database' => true,
            ],
            'approval_reminder' => [
                'email' => true,
                'push' => true,
                'database' => true,
            ],
            'low_stock_alert' => [
                'email' => true,
                'push' => false,
                'database' => true,
            ],
        ];
    }

    /**
     * Get all notification types with metadata.
     */
    private function getNotificationTypes(): array
    {
        return [
            [
                'key' => 'approval_required',
                'name' => 'Approval Required',
                'description' => 'When a document is assigned to you for approval',
                'category' => 'Approvals',
            ],
            [
                'key' => 'document_approved',
                'name' => 'Document Approved',
                'description' => 'When your document is approved',
                'category' => 'Approvals',
            ],
            [
                'key' => 'document_rejected',
                'name' => 'Document Rejected',
                'description' => 'When your document is rejected',
                'category' => 'Approvals',
            ],
            [
                'key' => 'approval_reminder',
                'name' => 'Approval Reminders',
                'description' => 'Daily reminders for pending approvals',
                'category' => 'Approvals',
            ],
            [
                'key' => 'low_stock_alert',
                'name' => 'Low Stock Alerts',
                'description' => 'When inventory items are running low',
                'category' => 'Inventory',
            ],
        ];
    }
}
