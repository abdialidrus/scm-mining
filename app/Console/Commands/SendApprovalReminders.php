<?php

namespace App\Console\Commands;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendApprovalReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approvals:send-reminders {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for pending approvals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!config('notifications.approval_reminder.enabled', true)) {
            $this->info('Approval reminders are disabled in configuration.');
            return;
        }

        $daysBefore = config('notifications.approval_reminder.days_before_escalation', 3);
        $cutoffDate = now()->subDays($daysBefore);

        $this->info("🔔 Sending Approval Reminders");
        $this->info("   Looking for approvals older than {$daysBefore} days ({$cutoffDate->format('Y-m-d H:i')})");
        $this->newLine();

        // Get pending approvals that are overdue
        $approvals = Approval::query()
            ->where('status', Approval::STATUS_PENDING)
            ->where('created_at', '<=', $cutoffDate)
            ->with(['approvable', 'step'])
            ->get();

        if ($approvals->isEmpty()) {
            $this->info('✓ No overdue approvals found. All good!');
            return;
        }

        $this->line("Found {$approvals->count()} overdue approval(s)");
        $this->newLine();

        // Group approvals by approver
        $approvalsByUser = [];
        $approvalsByRole = [];

        foreach ($approvals as $approval) {
            if ($approval->assigned_to_user_id) {
                $userId = $approval->assigned_to_user_id;
                if (!isset($approvalsByUser[$userId])) {
                    $approvalsByUser[$userId] = [];
                }
                $approvalsByUser[$userId][] = $approval;
            }

            if ($approval->assigned_to_role) {
                $role = $approval->assigned_to_role;
                if (!isset($approvalsByRole[$role])) {
                    $approvalsByRole[$role] = [];
                }
                $approvalsByRole[$role][] = $approval;
            }
        }

        $sentCount = 0;
        $errorCount = 0;

        // Send reminders to specific users
        foreach ($approvalsByUser as $userId => $userApprovals) {
            $user = User::find($userId);

            if (!$user) {
                continue;
            }

            $documents = $this->prepareDocumentData($userApprovals);
            $overdueCount = $this->countOverdue($userApprovals, $daysBefore + 2);

            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] Would send to: {$user->name} ({$user->email})");
                $this->line("            Pending: " . count($userApprovals) . " | Overdue: {$overdueCount}");
            } else {
                try {
                    $user->notify(new \App\Notifications\Approval\PendingApprovalReminderNotification(
                        $userApprovals,
                        $documents,
                        count($userApprovals),
                        $overdueCount
                    ));

                    $this->info("  ✓ Sent to: {$user->name} ({$user->email}) - {count($userApprovals)} pending");
                    $sentCount++;

                    Log::info('Approval reminder sent', [
                        'user_id' => $user->id,
                        'approval_count' => count($userApprovals),
                        'overdue_count' => $overdueCount,
                    ]);
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed for: {$user->name} - " . $e->getMessage());
                    $errorCount++;

                    Log::error('Failed to send approval reminder', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Send reminders to users with roles
        foreach ($approvalsByRole as $role => $roleApprovals) {
            $users = User::role($role)->get();

            foreach ($users as $user) {
                $documents = $this->prepareDocumentData($roleApprovals);
                $overdueCount = $this->countOverdue($roleApprovals, $daysBefore + 2);

                if ($this->option('dry-run')) {
                    $this->line("  [DRY RUN] Would send to: {$user->name} ({$user->email}) [Role: {$role}]");
                    $this->line("            Pending: " . count($roleApprovals) . " | Overdue: {$overdueCount}");
                } else {
                    try {
                        $user->notify(new \App\Notifications\Approval\PendingApprovalReminderNotification(
                            $roleApprovals,
                            $documents,
                            count($roleApprovals),
                            $overdueCount
                        ));

                        $this->info("  ✓ Sent to: {$user->name} ({$user->email}) [Role: {$role}] - " . count($roleApprovals) . " pending");
                        $sentCount++;

                        Log::info('Approval reminder sent (role-based)', [
                            'user_id' => $user->id,
                            'role' => $role,
                            'approval_count' => count($roleApprovals),
                        ]);
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed for: {$user->name} - " . $e->getMessage());
                        $errorCount++;

                        Log::error('Failed to send approval reminder', [
                            'user_id' => $user->id,
                            'role' => $role,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('✓ Dry run completed. No emails were actually sent.');
        } else {
            $this->info("✓ Reminder sending completed!");
            $this->info("   Sent: {$sentCount} | Errors: {$errorCount}");
        }
    }

    /**
     * Prepare document data for notification.
     */
    private function prepareDocumentData(array $approvals): array
    {
        $documents = [];

        foreach ($approvals as $approval) {
            $approvable = $approval->approvable;

            if (!$approvable) {
                continue;
            }

            $documents[] = [
                'number' => $approvable->document_number ?? 'N/A',
                'type' => $this->getDocumentType($approvable),
                'submitted_date' => $approval->created_at->format('d M Y'),
                'amount' => $this->formatAmount($approvable->total_amount ?? 0),
            ];
        }

        return $documents;
    }

    /**
     * Count overdue approvals.
     */
    private function countOverdue(array $approvals, int $days): int
    {
        $cutoff = now()->subDays($days);
        $count = 0;

        foreach ($approvals as $approval) {
            if ($approval->created_at <= $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get document type name.
     */
    private function getDocumentType($approvable): string
    {
        return match (get_class($approvable)) {
            'App\Models\PurchaseRequest' => 'PR',
            'App\Models\PurchaseOrder' => 'PO',
            'App\Models\GoodsReceipt' => 'GR',
            default => 'Document',
        };
    }

    /**
     * Format amount.
     */
    private function formatAmount($amount): string
    {
        return '$' . number_format($amount, 2);
    }
}
