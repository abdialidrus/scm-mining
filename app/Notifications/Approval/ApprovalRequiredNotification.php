<?php

namespace App\Notifications\Approval;

use App\Models\User;
use App\Notifications\BaseNotification;

class ApprovalRequiredNotification extends BaseNotification
{
    protected $approval;
    protected $document;
    protected $approver;

    public function __construct($approval, $document, User $approver)
    {
        $this->approval = $approval;
        $this->document = $document;
        $this->approver = $approver;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationType(): string
    {
        return 'approval_required';
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toMail($notifiable): array
    {
        $documentType = $this->getDocumentType();
        $documentUrl = $this->getDocumentUrl();

        return [
            'subject' => "Approval Required: {$documentType} {$this->document->document_number}",
            'view' => 'emails.approval.required',
            'data' => [
                'approverName' => $notifiable->name,
                'documentType' => $documentType,
                'documentNumber' => $this->document->document_number,
                'submittedBy' => $this->document->created_by_user->name ?? 'Unknown',
                'amount' => $this->formatAmount($this->document->total_amount ?? 0),
                'submittedDate' => $this->document->created_at->format('d M Y H:i'),
                'description' => $this->document->description ?? '',
                'approvalUrl' => $documentUrl,
                'dashboardUrl' => route('approvals.index'),
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
        $documentType = $this->getDocumentType();

        return [
            'title' => 'Approval Required',
            'body' => "{$documentType} {$this->document->document_number} requires your approval",
            'data' => [
                'type' => 'approval_required',
                'document_id' => $this->document->id,
                'document_type' => $this->getDocumentTypeKey(),
                'document_number' => $this->document->document_number,
            ],
            'options' => [
                'url' => $this->getDocumentUrl(),
                'icon' => asset('images/notification-icon.png'),
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
        $documentType = $this->getDocumentType();

        return [
            'title' => 'Approval Required',
            'message' => "{$documentType} {$this->document->document_number} requires your approval",
            'type' => 'approval_required',
            'document_id' => $this->document->id,
            'document_type' => $this->getDocumentTypeKey(),
            'document_number' => $this->document->document_number,
            'submitted_by' => $this->document->created_by_user->name ?? 'Unknown',
            'amount' => $this->document->total_amount ?? 0,
            'url' => $this->getDocumentUrl(),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get human-readable document type.
     */
    protected function getDocumentType(): string
    {
        return match ($this->document->getMorphClass()) {
            'App\Models\PurchaseRequest' => 'Purchase Request',
            'App\Models\PurchaseOrder' => 'Purchase Order',
            'App\Models\GoodsReceipt' => 'Goods Receipt',
            default => 'Document',
        };
    }

    /**
     * Get document type key.
     */
    protected function getDocumentTypeKey(): string
    {
        return match ($this->document->getMorphClass()) {
            'App\Models\PurchaseRequest' => 'purchase_request',
            'App\Models\PurchaseOrder' => 'purchase_order',
            'App\Models\GoodsReceipt' => 'goods_receipt',
            default => 'document',
        };
    }

    /**
     * Get document URL.
     */
    protected function getDocumentUrl(): string
    {
        $typeKey = $this->getDocumentTypeKey();
        $routeMap = [
            'purchase_request' => 'purchase-requests.show',
            'purchase_order' => 'purchase-orders.show',
            'goods_receipt' => 'goods-receipts.show',
        ];

        $route = $routeMap[$typeKey] ?? null;
        return $route ? route($route, $this->document->id) : '#';
    }

    /**
     * Format amount with currency.
     */
    protected function formatAmount($amount): string
    {
        return '$' . number_format($amount, 2);
    }
}
