<?php

namespace App\Console\Commands;

use App\Contracts\EmailServiceInterface;
use App\Models\User;
use App\Models\NotificationPreference;
use App\Services\Push\OneSignalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestNotificationSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test {--email} {--push} {--tables}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the notification system components';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Notification System...');
        $this->newLine();

        // Test database tables
        if ($this->option('tables') || !$this->option('email') && !$this->option('push')) {
            $this->testDatabaseTables();
        }

        // Test email service
        if ($this->option('email') || !$this->option('tables') && !$this->option('push')) {
            $this->testEmailService();
        }

        // Test push service
        if ($this->option('push') || !$this->option('tables') && !$this->option('email')) {
            $this->testPushService();
        }

        $this->newLine();
        $this->info('✅ All tests completed!');
    }

    /**
     * Test database tables
     */
    protected function testDatabaseTables()
    {
        $this->info('📋 Testing Database Tables...');

        $tables = [
            'notification_preferences',
            'notification_logs',
            'user_devices',
            'notifications', // Laravel default
        ];

        foreach ($tables as $table) {
            try {
                $exists = DB::getSchemaBuilder()->hasTable($table);
                if ($exists) {
                    $count = DB::table($table)->count();
                    $this->line("  ✓ Table '{$table}' exists ({$count} records)");
                } else {
                    $this->error("  ✗ Table '{$table}' not found!");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error checking '{$table}': " . $e->getMessage());
            }
        }

        $this->newLine();
    }

    /**
     * Test email service
     */
    protected function testEmailService()
    {
        $this->info('📧 Testing Email Service...');

        try {
            // Check configuration
            $driver = config('notifications.email_driver');
            $apiKey = config('services.resend.key');
            $fromAddress = config('mail.from.address');

            $this->line("  • Email Driver: {$driver}");
            $this->line("  • API Key: " . (empty($apiKey) ? '❌ Not set' : '✓ Set (' . substr($apiKey, 0, 10) . '...)'));
            $this->line("  • From Address: {$fromAddress}");

            // Test with first user
            $user = User::first();

            if (!$user) {
                $this->warn("  ⚠ No users found in database. Skipping email send test.");
                $this->newLine();
                return;
            }

            $this->line("  • Test recipient: {$user->email}");

            if ($this->confirm('  Send test email to ' . $user->email . '?', false)) {
                $emailService = app(EmailServiceInterface::class);

                $result = $emailService->sendTemplate(
                    $user->email,
                    'Test Notification System',
                    'emails.approval.required',
                    [
                        'approverName' => $user->name,
                        'documentType' => 'Test Document',
                        'documentNumber' => 'TEST-001',
                        'submittedBy' => 'System Test',
                        'amount' => '$1,000.00',
                        'submittedDate' => now()->format('d M Y H:i'),
                        'description' => 'This is a test email from the notification system.',
                        'approvalUrl' => config('app.url'),
                        'dashboardUrl' => config('app.url'),
                    ]
                );

                if ($result['status'] === 'sent') {
                    $this->info('  ✓ Email sent successfully!');
                    $this->line('    Message ID: ' . ($result['message_id'] ?? 'N/A'));
                    $this->line('    Provider: ' . ($result['provider'] ?? 'N/A'));
                } else {
                    $this->error('  ✗ Email failed to send');
                }
            }
        } catch (\Exception $e) {
            $this->error('  ✗ Email test failed: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Test push notification service
     */
    protected function testPushService()
    {
        $this->info('🔔 Testing Push Notification Service...');

        try {
            // Check configuration
            $appId = config('services.onesignal.app_id');
            $apiKey = config('services.onesignal.api_key');

            $this->line('  • OneSignal App ID: ' . (empty($appId) ? '❌ Not set' : '✓ Set'));
            $this->line('  • OneSignal API Key: ' . (empty($apiKey) ? '❌ Not set' : '✓ Set (' . substr($apiKey, 0, 15) . '...)'));

            if (empty($appId) || empty($apiKey)) {
                $this->warn('  ⚠ OneSignal not configured. Skipping push test.');
                $this->newLine();
                return;
            }

            // Check for registered devices
            $deviceCount = DB::table('user_devices')->where('is_active', true)->count();
            $this->line("  • Active devices registered: {$deviceCount}");

            if ($deviceCount === 0) {
                $this->warn('  ⚠ No active devices registered. Use the frontend to register a device first.');
                $this->newLine();
                return;
            }

            // Test OneSignal connection
            $this->line('  • Testing OneSignal API connection...');

            $oneSignal = new OneSignalService();

            $user = User::whereHas('devices', function ($query) {
                $query->where('is_active', true);
            })->first();

            if (!$user) {
                $this->warn('  ⚠ No users with active devices found.');
                $this->newLine();
                return;
            }

            $this->line("  • Test user: {$user->name}");

            if ($this->confirm('  Send test push notification to ' . $user->name . '?', false)) {
                $result = $oneSignal->sendToUsers(
                    [$user->id],
                    'Test Notification',
                    'This is a test notification from SCM Mining System',
                    [
                        'type' => 'test',
                        'timestamp' => now()->toISOString(),
                    ],
                    [
                        'url' => config('app.url'),
                    ]
                );

                if ($result['success']) {
                    $this->info('  ✓ Push notification sent successfully!');
                    $this->line('    Notification ID: ' . ($result['notification_id'] ?? 'N/A'));
                    $this->line('    Recipients: ' . ($result['recipients'] ?? 0));
                } else {
                    $this->error('  ✗ Push notification failed: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
        } catch (\Exception $e) {
            $this->error('  ✗ Push test failed: ' . $e->getMessage());
        }

        $this->newLine();
    }
}
