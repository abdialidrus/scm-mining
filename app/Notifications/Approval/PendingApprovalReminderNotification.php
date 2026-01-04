<?php

namespace App\Notifications\Approval;

use App\Notifications\BaseNotification;

class PendingApprovalReminderNotification extends BaseNotification
{
    protected $approvals;
    protected $documents;
    protected int $pendingCount;
    protected int $overdueCount;

    public function __construct($approvals, array $documents, int $pendingCount, int $overdueCount)
    {
        $this->approvals = $approvals;
        $this->documents = $documents;
        $this->pendingCount = $pendingCount;
        $this->overdueCount = $overdueCount;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationType(): string
    {
        return 'approval_reminder';
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toMail($notifiable): array
    {
        return [
            'subject' => "Reminder: You have {$this->pendingCount} pending approval(s)",
            'view' => 'emails.approval.reminder',
            'data' => [
                'approverName' => $notifiable->name,
                'pendingCount' => $this->pendingCount,
                'overdueCount' => $this->overdueCount,
                'documents' => $this->documents,
                'dashboardUrl' => route('approvals.index'),
                'settingsUrl' => config('app.url') . '/settings/notifications',
            ],
        ];
    }

    /**
     * Get the push notification representation.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toPush($notifiable): array
    {
        $message = $this->overdueCount > 0
            ? "You have {$this->pendingCount} pending approvals ({$this->overdueCount} overdue)"
            : "You have {$this->pendingCount} pending approvals";

        return [
            'title' => '⏰ Approval Reminder',
            'body' => $message,
            'data' => [
                'type' => 'approval_reminder',
                'pending_count' => $this->pendingCount,
                'overdue_count' => $this->overdueCount,
            ],
            'options' => [
                'url' => route('approvals.index'),
                'icon' => asset('images/reminder-icon.png'),
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Pending Approvals Reminder',
            'message' => "You have {$this->pendingCount} pending approval(s) requiring your attention",
            'type' => 'approval_reminder',
            'pending_count' => $this->pendingCount,
            'overdue_count' => $this->overdueCount,
            'documents' => $this->documents,
            'url' => route('approvals.index'),
            'created_at' => now()->toISOString(),
        ];
    }
}
