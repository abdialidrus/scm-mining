<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Configure which notification channels are enabled globally.
    |
    */

    'channels' => [
        'email' => env('NOTIFICATION_EMAIL_ENABLED', true),
        'database' => env('NOTIFICATION_DATABASE_ENABLED', true),
        'push' => env('NOTIFICATION_PUSH_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for notifications.
    |
    */

    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
        'queue' => env('NOTIFICATION_QUEUE_NAME', 'notifications'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Driver
    |--------------------------------------------------------------------------
    |
    | Configure which email driver to use: resend, smtp
    |
    */

    'email_driver' => env('EMAIL_DRIVER', 'resend'),

    /*
    |--------------------------------------------------------------------------
    | Approval Reminder Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic reminder notifications for pending approvals.
    |
    */

    'approval_reminder' => [
        'enabled' => env('APPROVAL_REMINDER_ENABLED', true),
        'days_before_escalation' => env('APPROVAL_REMINDER_DAYS', 3),
        'schedule' => 'daily', // Runs at 9 AM daily
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Alert Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic stock level alerts.
    |
    */

    'stock_alert' => [
        'enabled' => env('STOCK_ALERT_ENABLED', true),
        'low_stock_threshold' => env('STOCK_ALERT_LOW_THRESHOLD', 10),
        'schedule' => 'daily', // Runs at 8 AM daily
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Types
    |--------------------------------------------------------------------------
    |
    | Define all available notification types in the system.
    |
    */

    'types' => [
        'approval_required' => 'Approval Required',
        'document_approved' => 'Document Approved',
        'document_rejected' => 'Document Rejected',
        'approval_reminder' => 'Approval Reminder',
        'low_stock_alert' => 'Low Stock Alert',
        'out_of_stock_alert' => 'Out of Stock Alert',
        'pr_submitted' => 'Purchase Request Submitted',
        'po_submitted' => 'Purchase Order Submitted',
        'gr_posted' => 'Goods Receipt Posted',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Preferences
    |--------------------------------------------------------------------------
    |
    | Default notification preferences for new users.
    |
    */

    'default_preferences' => [
        'email_enabled' => true,
        'database_enabled' => true,
        'push_enabled' => true,
    ],
];
