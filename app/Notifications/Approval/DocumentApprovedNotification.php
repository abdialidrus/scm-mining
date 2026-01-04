<?php

namespace App\Notifications\Approval;

use App\Models\User;
use App\Notifications\BaseNotification;

class DocumentApprovedNotification extends BaseNotification
{
    protected $approval;
    protected $document;
    protected $approver;
    protected bool $isFinalApproval;

    public function __construct($approval, $document, User $approver, bool $isFinalApproval = false)
    {
        $this->approval = $approval;
        $this->document = $document;
        $this->approver = $approver;
        $this->isFinalApproval = $isFinalApproval;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationType(): string
    {
        return 'document_approved';
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

        return [
            'subject' => "{$documentType} {$this->document->document_number} Approved",
            'view' => 'emails.approval.approved',
            'data' => [
                'submitterName' => $notifiable->name,
                'documentType' => $documentType,
                'documentNumber' => $this->document->document_number,
                'approvedBy' => $this->approver->name,
                'approvedDate' => now()->format('d M Y H:i'),
                'comments' => $this->approval->comments ?? '',
                'currentStep' => $this->approval->approval_step_number ?? 1,
                'totalSteps' => $this->getTotalSteps(),
                'isFinalApproval' => $this->isFinalApproval,
                'documentUrl' => $this->getDocumentUrl(),
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
        $message = $this->isFinalApproval
            ? "{$documentType} {$this->document->document_number} is fully approved!"
            : "{$documentType} {$this->document->document_number} approved by {$this->approver->name}";

        return [
            'title' => '✓ Document Approved',
            'body' => $message,
            'data' => [
                'type' => 'document_approved',
                'document_id' => $this->document->id,
                'document_type' => $this->getDocumentTypeKey(),
                'document_number' => $this->document->document_number,
                'is_final' => $this->isFinalApproval,
            ],
            'options' => [
                'url' => $this->getDocumentUrl(),
                'icon' => asset('images/success-icon.png'),
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
            'title' => 'Document Approved',
            'message' => "{$documentType} {$this->document->document_number} has been approved",
            'type' => 'document_approved',
            'document_id' => $this->document->id,
            'document_type' => $this->getDocumentTypeKey(),
            'document_number' => $this->document->document_number,
            'approved_by' => $this->approver->name,
            'is_final_approval' => $this->isFinalApproval,
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
     * Get total approval steps.
     */
    protected function getTotalSteps(): int
    {
        // This should be calculated from approval workflow
        return $this->approval->total_steps ?? 1;
    }
}
