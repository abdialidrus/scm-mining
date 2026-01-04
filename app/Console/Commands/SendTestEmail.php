<?php

namespace App\Console\Commands;

use App\Contracts\EmailServiceInterface;
use Illuminate\Console\Command;

class SendTestEmail extends Command
{
    protected $signature = 'notification:send-test-email {email}';
    protected $description = 'Send a test email to verify Resend integration';

    public function handle()
    {
        $email = $this->argument('email');

        $this->info("📧 Sending test email to: {$email}");

        try {
            $emailService = app(EmailServiceInterface::class);

            $result = $emailService->sendTemplate(
                $email,
                '🧪 SCM Mining - Test Email',
                'emails.approval.required',
                [
                    'approverName' => 'Test User',
                    'documentType' => 'Test Purchase Request',
                    'documentNumber' => 'TEST-PR-001',
                    'submittedBy' => 'System Administrator',
                    'amount' => '$5,000.00',
                    'submittedDate' => now()->format('d M Y H:i'),
                    'description' => 'This is a test email to verify that the notification system is working correctly with Resend.',
                    'approvalUrl' => config('app.url') . '/approvals',
                    'dashboardUrl' => config('app.url') . '/dashboard',
                ]
            );

            if ($result['status'] === 'sent') {
                $this->info('✅ Email sent successfully!');
                $this->line('   Provider: ' . $result['provider']);
                $this->line('   Message ID: ' . ($result['message_id'] ?? 'N/A'));
                $this->newLine();
                $this->info('📬 Please check your inbox at: ' . $email);
            } else {
                $this->error('❌ Failed to send email');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();

            if (str_contains($e->getMessage(), 'can only send testing emails')) {
                $this->warn('⚠️  Resend is in test mode!');
                $this->warn('   You can only send emails to: muhammadabdi25@gmail.com');
                $this->warn('   To send to other emails, verify your domain at:');
                $this->warn('   https://resend.com/domains');
            }
        }
    }
}
