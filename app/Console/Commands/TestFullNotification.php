<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\PurchaseRequest;
use App\Notifications\Approval\ApprovalRequiredNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestFullNotification extends Command
{
    protected $signature = 'notification:test-full';
    protected $description = 'Test complete notification flow (email + database + logging)';

    public function handle()
    {
        $this->info('🧪 Testing Full Notification Flow...');
        $this->newLine();

        // Get test user
        $user = User::where('email', 'muhammadabdi25@gmail.com')->first()
            ?? User::first();

        if (!$user) {
            $this->error('❌ No users found in database');
            return;
        }

        $this->line("📧 Test User: {$user->name} ({$user->email})");
        $this->newLine();

        // Try to get a real PR, or create test data
        $pr = PurchaseRequest::first();

        if (!$pr) {
            $this->warn('⚠️  No Purchase Requests found in database');
            $this->line('   Creating mock test data...');
            $this->newLine();

            // Use simplified test without actual PR
            $this->testSimpleEmail($user);
            return;
        }

        $this->line('📝 Using Real Document:');
        $this->line("   Document: {$pr->document_number}");
        $this->line("   Amount: \${$pr->total_amount}");
        $this->newLine();

        try {
            // Check notification preferences
            $this->line('🔍 Checking User Notification Preferences...');
            $prefs = DB::table('notification_preferences')
                ->where('user_id', $user->id)
                ->get();

            if ($prefs->isEmpty()) {
                $this->line('   ℹ️  No preferences set (will use defaults: all enabled)');
            } else {
                foreach ($prefs as $pref) {
                    $this->line("   • {$pref->notification_type}: Email={$pref->email_enabled}, DB={$pref->database_enabled}, Push={$pref->push_enabled}");
                }
            }
            $this->newLine();

            // Create mock approval
            $mockApproval = (object) [
                'id' => 999,
                'approval_step_number' => 1,
                'comments' => null,
                'total_steps' => 2,
            ];

            // Send notification
            $this->line('📤 Sending Notification...');

            $notification = new ApprovalRequiredNotification(
                $mockApproval,
                $pr,
                $user
            );

            $user->notify($notification);

            $this->info('✅ Notification dispatched!');
            $this->newLine();

            // Wait a bit
            sleep(1);

            $this->displayResults($user);
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('   ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    protected function testSimpleEmail($user)
    {
        $this->line('📤 Sending Simple Test Email...');

        try {
            $emailService = app(\App\Contracts\EmailServiceInterface::class);

            $result = $emailService->sendTemplate(
                $user->email === 'eng.head@demo.test' ? 'muhammadabdi25@gmail.com' : $user->email,
                'Test Notification - SCM Mining',
                'emails.approval.required',
                [
                    'approverName' => $user->name,
                    'documentType' => 'Test Purchase Request',
                    'documentNumber' => 'TEST-' . now()->format('YmdHis'),
                    'submittedBy' => 'System Test',
                    'amount' => '$10,000.00',
                    'submittedDate' => now()->format('d M Y H:i'),
                    'description' => 'This is a test notification to verify the email system.',
                    'approvalUrl' => config('app.url'),
                    'dashboardUrl' => config('app.url'),
                ]
            );

            if ($result['status'] === 'sent') {
                $this->info('✅ Email sent successfully!');
                $this->line('   Provider: ' . $result['provider']);
                $this->line('   Message ID: ' . ($result['message_id'] ?? 'N/A'));
                $this->newLine();
                $this->info('📬 Check your inbox!');
            }
        } catch (\Exception $e) {
            $this->error('❌ Email failed: ' . $e->getMessage());
        }
    }

    protected function displayResults($user)
    {
        // Check results
        $this->line('📊 Checking Results...');
        $this->newLine();

        // Check database notifications
        $dbNotification = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($dbNotification) {
            $this->info('✅ Database Notification Created:');
            $this->line('   ID: ' . $dbNotification->id);
            $this->line('   Type: ' . class_basename($dbNotification->type));
            $this->line('   Created: ' . $dbNotification->created_at);
            $data = json_decode($dbNotification->data, true);
            $this->line('   Message: ' . ($data['message'] ?? 'N/A'));
        } else {
            $this->warn('⚠️  No database notification found');
        }
        $this->newLine();

        // Check notification logs
        $logs = DB::table('notification_logs')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $this->info('📋 Notification Logs (last 5):');
        if ($logs->isEmpty()) {
            $this->warn('   ⚠️  No logs found');
        } else {
            foreach ($logs as $log) {
                $status = $log->status === 'sent' ? '✅' : '❌';
                $this->line("   {$status} {$log->channel} via {$log->provider} - {$log->status}");
                if ($log->error_message) {
                    $this->line('      Error: ' . substr($log->error_message, 0, 100));
                }
            }
        }
        $this->newLine();

        // Summary
        $totalLogs = DB::table('notification_logs')->count();
        $totalNotifications = DB::table('notifications')->count();

        $this->info('📈 System Summary:');
        $this->line("   Total Notification Logs: {$totalLogs}");
        $this->line("   Total Database Notifications: {$totalNotifications}");
        $this->newLine();

        $this->info('✅ Test completed!');
    }
}
